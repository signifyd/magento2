<?php

namespace Signifyd\Connect\Model\PreAuth\RootwaysAuthorizeCim;

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
     * Handle Rootways Authorize Cim
     *
     * @param array $checkoutPaymentDetails
     * @param array $dataArray
     * @param Quote $quote
     * @return array
     */
    public function handle(array $checkoutPaymentDetails, array $dataArray, Quote $quote): array
    {
        $this->logger->info(
            "Collecting Checkout Payment Details using the RootwaysAuthorizeCim Handler", ['entity' => $quote]
        );
        $additionalData = $dataArray['paymentMethod']['additional_data'];

        $checkoutPaymentDetails['holderName'] = $additionalData['holderName'] ?? null;
        $checkoutPaymentDetails['cardExpiryMonth'] = $additionalData['cc_exp_month'] ?? null;
        $checkoutPaymentDetails['cardExpiryYear'] = $additionalData['cc_exp_year'] ?? null;

        $ccNumber = $additionalData['cc_number'] ?? null;
        if ($ccNumber) {
            $checkoutPaymentDetails['cardLast4'] = substr($ccNumber, -4);
            $checkoutPaymentDetails['cardBin'] = substr($ccNumber, 0, 6);
        } else {
            $checkoutPaymentDetails['cardLast4'] = null;
            $checkoutPaymentDetails['cardBin'] = $additionalData['card_bin'] ?? null;
        }

        return $checkoutPaymentDetails;
    }
}
