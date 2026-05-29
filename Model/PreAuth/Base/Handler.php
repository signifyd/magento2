<?php

namespace Signifyd\Connect\Model\PreAuth\Base;

use Magento\Quote\Model\Quote;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\PreAuth\CheckoutPaymentDetailsMapperInterface;

class Handler implements CheckoutPaymentDetailsMapperInterface
{
    /**
     * @var Logger
     */
    protected $logger;

    /**
     * Handler constructor.
     *
     * @param Logger $logger
     */
    public function __construct(
        Logger $logger
    ) {
        $this->logger = $logger;
    }

    /**
     * Handle Base
     *
     * @param array $checkoutPaymentDetails
     * @param array $dataArray
     * @param Quote $quote
     * @return array
     */
    public function handle(array $checkoutPaymentDetails, array $dataArray, Quote $quote): array
    {
        $this->logger->info("Collecting Checkout Payment Details using the base Handler", ['entity' => $quote]);
        $additionalData = $dataArray['paymentMethod']['additional_data'];

        $checkoutPaymentDetails['cardBin'] = $additionalData['cardBin'] ?? null;
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
