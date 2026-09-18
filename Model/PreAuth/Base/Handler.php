<?php

namespace Signifyd\Connect\Model\PreAuth\Base;

use Magento\Quote\Model\Quote;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\PreAuth\CheckoutPaymentDetailsMapperInterface;
use Signifyd\Connect\Model\PreAuth\QuotePaymentDetailsMapperInterface;

class Handler implements CheckoutPaymentDetailsMapperInterface, QuotePaymentDetailsMapperInterface
{
    /**
     * Payment method the stored payment data was collected for
     */
    public const PAYMENT_METHOD_KEY = 'signifydPaymentMethod';

    /**
     * Payment data collected on checkout, as stored on the quote payment additional information
     */
    public const QUOTE_PAYMENT_KEYS = [
        'cardBin',
        'holderName',
        'cardLast4',
        'cardExpiryMonth',
        'cardExpiryYear'
    ];

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

    /**
     * Handle Base for checkouts which store the payment data on the quote payment
     *
     * @param array $checkoutPaymentDetails
     * @param Quote $quote
     * @return array
     */
    public function handleQuotePayment(array $checkoutPaymentDetails, Quote $quote): array
    {
        $this->logger->info(
            "Collecting Checkout Payment Details from the quote payment using the base Handler",
            ['entity' => $quote]
        );

        $payment = $quote->getPayment();

        if (empty($payment)) {
            return $checkoutPaymentDetails;
        }

        $paymentMethod = $payment->getAdditionalInformation(self::PAYMENT_METHOD_KEY);

        // Data collected for a payment method the shopper did not place the order with is not used
        $isSamePaymentMethod = $paymentMethod === null || $paymentMethod === $payment->getMethod();

        foreach (self::QUOTE_PAYMENT_KEYS as $key) {
            $checkoutPaymentDetails[$key] = $isSamePaymentMethod ? $payment->getAdditionalInformation($key) : null;
        }

        return $checkoutPaymentDetails;
    }
}
