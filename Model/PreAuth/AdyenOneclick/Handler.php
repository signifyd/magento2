<?php

namespace Signifyd\Connect\Model\PreAuth\AdyenOneclick;

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
     * Handle Adyen Oneclick
     *
     * @param array $checkoutPaymentDetails
     * @param array $dataArray
     * @param Quote $quote
     * @return array
     */
    public function handle(array $checkoutPaymentDetails, array $dataArray, Quote $quote): array
    {
        $this->logger->info(
            "Collecting Checkout Payment Details using the AdyenOneClick Handler", ['entity' => $quote]
        );
        $additionalData = $dataArray['paymentMethod']['additional_data'];

        if (isset($additionalData['stateData'])) {
            try {
                $stateData = $this->jsonSerializer->unserialize($additionalData['stateData']);

                /** @var \Adyen\Payment\Model\Api\PaymentRequest $paymentRequest */
                $paymentRequest = $this->objectManager->create(\Adyen\Payment\Model\Api\PaymentRequest::class);

                $customerId = $quote->getCustomer()->getId();
                $shopperReference = $customerId < 100
                    ? str_pad($customerId, 3, 0, STR_PAD_LEFT)
                    : $customerId;

                $contracts = $paymentRequest->getRecurringContractsForShopper(
                    $shopperReference,
                    $quote->getStoreId()
                );

                if (isset($stateData['paymentMethod']['storedPaymentMethodId'])) {
                    $storedPaymentMethodId = $stateData['paymentMethod']['storedPaymentMethodId'];
                    $checkoutPaymentDetails['cardBin'] =
                        $contracts[$storedPaymentMethodId]['additionalData']['cardBin'] ?? null;
                } else {
                    $checkoutPaymentDetails['cardBin'] = null;
                }
            } catch (\Exception $e) {
                $this->logger->debug(
                    'adyen_oneclick: unable to get cardBin from stateData: ' . $e->getMessage(),
                    ['entity' => $quote]
                );
                $checkoutPaymentDetails['cardBin'] = null;
            }
        } else {
            $checkoutPaymentDetails['cardBin'] = null;
        }

        $checkoutPaymentDetails['holderName'] = $additionalData['holderName'] ?? null;
        $checkoutPaymentDetails['cardLast4'] = $additionalData['cardLast4'] ?? null;

        if (isset($additionalData['expDate'])) {
            $expDate = explode('-', $additionalData['expDate']);
            $checkoutPaymentDetails['cardExpiryMonth'] = $expDate[0];
            $checkoutPaymentDetails['cardExpiryYear'] = $expDate[1];
        } else {
            $checkoutPaymentDetails['cardExpiryMonth'] = $additionalData['cardExpiryMonth'] ?? null;
            $checkoutPaymentDetails['cardExpiryYear'] = $additionalData['cardExpiryYear'] ?? null;
        }

        return $checkoutPaymentDetails;
    }
}
