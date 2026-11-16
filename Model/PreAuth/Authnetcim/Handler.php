<?php

namespace Signifyd\Connect\Model\PreAuth\Authnetcim;

use Magento\Framework\ObjectManagerInterface;
use Magento\Quote\Model\Quote;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\JsonSerializer;
use Signifyd\Connect\Model\PreAuth\Base\Handler as BaseHandler;

class Handler extends BaseHandler
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

        parent::__construct($logger);
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
            "Collecting Checkout Payment Details using the Authnetcim Handler",
            ['entity' => $quote]
        );
        $requestAdditionalData = $dataArray['paymentMethod']['additional_data'];
        $additionalData = $requestAdditionalData;

        if (isset($requestAdditionalData['card_id'])) {
            $cardData = $this->getCardData($requestAdditionalData['card_id'], 'hash', $quote);

            if (empty($cardData) === false) {
                $additionalData = $cardData;
            }
        }

        $checkoutPaymentDetails['holderName'] = $requestAdditionalData['holderName'] ?? null;
        $checkoutPaymentDetails['cardExpiryMonth'] = $additionalData['cc_exp_month'] ?? null;
        $checkoutPaymentDetails['cardExpiryYear'] = $additionalData['cc_exp_year'] ?? null;
        $checkoutPaymentDetails['cardLast4'] = $additionalData['cc_last4'] ?? null;
        $checkoutPaymentDetails['cardBin'] = $additionalData['cc_bin'] ?? null;

        return $checkoutPaymentDetails;
    }

    /**
     * Handle Authnetcim for checkouts which store the payment data on the quote payment
     *
     * @param array $checkoutPaymentDetails
     * @param Quote $quote
     * @return array
     */
    public function handleQuotePayment(array $checkoutPaymentDetails, Quote $quote): array
    {
        $checkoutPaymentDetails = parent::handleQuotePayment($checkoutPaymentDetails, $quote);

        $this->logger->info(
            "Collecting Checkout Payment Details from the stored card using the Authnetcim Handler",
            ['entity' => $quote]
        );

        $payment = $quote->getPayment();

        if (empty($payment)) {
            return $checkoutPaymentDetails;
        }

        $cardId = $payment->getAdditionalInformation('card_id');
        $cardData = [];

        if (empty($cardId) === false) {
            $cardData = $this->getCardData($cardId, 'hash', $quote);
        }

        if (empty($cardData) && empty($payment->getData('tokenbase_id')) === false) {
            $cardData = $this->getCardData($payment->getData('tokenbase_id'), 'id', $quote);
        }

        if (empty($cardData)) {
            return $checkoutPaymentDetails;
        }

        $cardDataMap = [
            'cardBin' => 'cc_bin',
            'cardLast4' => 'cc_last4',
            'cardExpiryMonth' => 'cc_exp_month',
            'cardExpiryYear' => 'cc_exp_year'
        ];

        foreach ($cardDataMap as $detailKey => $cardKey) {
            if (empty($checkoutPaymentDetails[$detailKey]) && isset($cardData[$cardKey])) {
                $checkoutPaymentDetails[$detailKey] = $cardData[$cardKey];
            }
        }

        return $checkoutPaymentDetails;
    }

    /**
     * Load the additional data of a stored card
     *
     * @param mixed $cardId
     * @param string $field
     * @param Quote $quote
     * @return array
     */
    public function getCardData($cardId, $field, Quote $quote): array
    {
        try {
            /** @var \ParadoxLabs\TokenBase\Model\ResourceModel\Card\Collection $cardCollection */
            $cardCollection = $this->objectManager->create(
                \ParadoxLabs\TokenBase\Model\ResourceModel\Card\CollectionFactory::class
            )->create()
                ->addFieldToFilter($field, ['eq' => $cardId]);

            $card = $cardCollection->getFirstItem();

            if (empty($card->getData('additional'))) {
                return [];
            }

            $cardData = $this->jsonSerializer->unserialize($card->getData('additional'));

            return is_array($cardData) ? $cardData : [];
        } catch (\Exception $e) {
            $this->logger->debug(
                'authnetcim: unable to read the stored card data: ' . $e->getMessage(),
                ['entity' => $quote]
            );

            return [];
        }
    }
}
