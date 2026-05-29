<?php

namespace Signifyd\Connect\Model\PreAuth\Authnetcim;

use Magento\Framework\ObjectManagerInterface;
use Magento\Quote\Model\Quote;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\JsonSerializer;
use Signifyd\Connect\Model\PreAuth\CheckoutPaymentDetailsMapperInterface;

class Handler implements CheckoutPaymentDetailsMapperInterface
{
    /**
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @var JsonSerializer
     */
    protected $jsonSerializer;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * Handler constructor.
     *
     * @param ObjectManagerInterface $objectManager
     * @param JsonSerializer $jsonSerializer
     * @param Logger $logger
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        JsonSerializer $jsonSerializer,
        Logger $logger
    ) {
        $this->objectManager = $objectManager;
        $this->jsonSerializer = $jsonSerializer;
        $this->logger = $logger;
    }

    /**
     * Handle Authnetcim
     *
     * @param array $checkoutPaymentDetails
     * @param array $dataArray
     * @param Quote $quote
     * @return array
     */
    public function handle(array $checkoutPaymentDetails, array $dataArray, Quote $quote): array
    {
        $this->logger->info(
            "Collecting Checkout Payment Details using the Authnetcim Handler", ['entity' => $quote]
        );
        $requestAdditionalData = $dataArray['paymentMethod']['additional_data'];
        $additionalData = $requestAdditionalData;

        if (isset($requestAdditionalData['card_id'])) {
            /** @var \ParadoxLabs\TokenBase\Model\ResourceModel\Card\Collection $cardCollection */
            $cardCollection = $this->objectManager->create(
                \ParadoxLabs\TokenBase\Model\ResourceModel\Card\CollectionFactory::class
            )->create()
                ->addFieldToFilter('hash', ['eq' => $requestAdditionalData['card_id']]);

            $card = $cardCollection->getFirstItem();

            if (empty($card->getData('additional')) === false) {
                try {
                    $additionalData = $this->jsonSerializer->unserialize($card->getData('additional'));
                } catch (\Exception $e) {
                    $this->logger->debug(
                        'authnetcim: unable to unserialize card additional data: ' . $e->getMessage(),
                        ['entity' => $quote]
                    );
                }
            }
        }

        $checkoutPaymentDetails['holderName'] = $requestAdditionalData['holderName'] ?? null;
        $checkoutPaymentDetails['cardExpiryMonth'] = $additionalData['cc_exp_month'] ?? null;
        $checkoutPaymentDetails['cardExpiryYear'] = $additionalData['cc_exp_year'] ?? null;
        $checkoutPaymentDetails['cardLast4'] = $additionalData['cc_last4'] ?? null;
        $checkoutPaymentDetails['cardBin'] = $additionalData['cc_bin'] ?? null;

        return $checkoutPaymentDetails;
    }
}
