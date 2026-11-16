<?php

namespace Signifyd\Connect\Model\Api;

use Magento\Framework\Stdlib\DateTime\DateTimeFactory;
use Magento\Sales\Model\Order;

class ChargebackCreateRequest
{
    /**
     * Highest chargeback fee amount the API accepts, higher values fail validation.
     */
    public const MAX_CHARGEBACK_FEE_AMOUNT = 30;

    /**
     * @var DateTimeFactory
     */
    public $dateTimeFactory;

    /**
     * @var AssumedOwnershipFactory
     */
    public $assumedOwnershipFactory;

    /**
     * ChargebackCreateRequest construct.
     *
     * @param DateTimeFactory $dateTimeFactory
     * @param AssumedOwnershipFactory $assumedOwnershipFactory
     */
    public function __construct(
        DateTimeFactory $dateTimeFactory,
        AssumedOwnershipFactory $assumedOwnershipFactory
    ) {
        $this->dateTimeFactory = $dateTimeFactory;
        $this->assumedOwnershipFactory = $assumedOwnershipFactory;
    }

    /**
     * Construct a new ChargebackCreateRequest object
     *
     * @param Order $order
     * @param array $chargebackData
     * @return []
     */
    public function __invoke(Order $order, array $chargebackData)
    {
        $dateTime = $this->dateTimeFactory->create();
        $now = gmdate('c', $dateTime->gmtTimestamp());

        $chargebackAmount   = $chargebackData['chargebackAmount'] ?? $order->getGrandTotal();
        $chargebackCurrency = $chargebackData['chargebackCurrency'] ?? $order->getOrderCurrencyCode();

        $amount = [];
        $amount['amount'] = (float) $chargebackAmount;
        $amount['currencyCode'] = (string) $chargebackCurrency;

        $createRequest = [];
        $createRequest['amount'] = $amount;

        if (empty($chargebackData['paymentProcessor']) === false
            || empty($chargebackData['reasonCode']) === false
            || empty($chargebackData['reasonDescription']) === false
        ) {
            $createRequest['reason'] = $this->makeReason($chargebackData);
        }

        $issuerReportedDate = $this->makeIso8601Date($chargebackData['chargebackDate'] ?? null);
        $createRequest['issuerReportedDate'] = $issuerReportedDate ?? $now;

        $dueDate = $this->makeIso8601Date($chargebackData['disputeDeadlineAt'] ?? null);

        if (isset($dueDate)) {
            $createRequest['dueDate'] = $dueDate;
        }

        if (empty($chargebackData['paymentProcessor']) === false) {
            $createRequest['paymentProcessor'] = $chargebackData['paymentProcessor'];
        }

        if (empty($chargebackData['paymentNetwork']) === false) {
            $createRequest['paymentNetwork'] = $chargebackData['paymentNetwork'];
        }

        if (empty($chargebackData['chargebackStage']) === false) {
            $createRequest['chargebackStage'] = $chargebackData['chargebackStage'];
        }

        /**
         * Cardholder details are reported by the payment processor on the dispute evidence, which
         * is filled in by the merchant and often comes empty. The order holds the same information
         * and is always available, so it is used as the fallback.
         */
        $cardholderEmail = $chargebackData['cardholderEmail'] ?? null;

        if (empty($cardholderEmail)) {
            $cardholderEmail = $order->getCustomerEmail();
        }

        if (empty($cardholderEmail) === false) {
            $createRequest['cardholderEmail'] = $cardholderEmail;
        }

        $cardholderName = $chargebackData['cardholderName'] ?? null;

        if (empty($cardholderName)) {
            $cardholderName = $this->getOrderCardholderName($order);
        }

        if (empty($cardholderName) === false) {
            $createRequest['cardholderName'] = $cardholderName;
        }

        if (empty($chargebackData['chargebackFeeAmount']) === false) {
            $chargebackFees = [];
            // Capped because the API rejects the whole request when the fee is above the limit
            $chargebackFees['amount'] = (float) min(
                (float) $chargebackData['chargebackFeeAmount'],
                self::MAX_CHARGEBACK_FEE_AMOUNT
            );
            $chargebackFees['currencyCode'] =
                (string) ($chargebackData['chargebackFeeCurrency'] ?? $chargebackCurrency);

            $createRequest['chargebackFees'] = $chargebackFees;
        }

        if (empty($chargebackData['assumedOwner']) === false) {
            $createRequest['assumedOwnership'] =
                $this->assumedOwnershipFactory->create()($chargebackData['assumedOwner']);
        }

        return $createRequest;
    }

    /**
     * Convert a date to the UTC ISO 8601 format the API expects.
     *
     * Rendered with gmdate so that the reported offset always matches the instant being sent,
     * no matter the time zone the store is running on.
     *
     * @param mixed $date
     * @return string|null
     */
    public function makeIso8601Date($date)
    {
        if (empty($date)) {
            return null;
        }

        $timestamp = is_numeric($date) ? (int) $date : strtotime((string) $date);

        if (empty($timestamp)) {
            return null;
        }

        return gmdate('c', $timestamp);
    }

    /**
     * Get the cardholder name from the order.
     *
     * @param Order $order
     * @return string|null
     */
    public function getOrderCardholderName(Order $order)
    {
        $billingAddress = $order->getBillingAddress();

        if (isset($billingAddress)) {
            $billingName = trim(
                (string) $billingAddress->getFirstname() . ' ' . (string) $billingAddress->getLastname()
            );

            if (empty($billingName) === false) {
                return $billingName;
            }
        }

        $customerName = trim(
            (string) $order->getCustomerFirstname() . ' ' . (string) $order->getCustomerLastname()
        );

        return empty($customerName) ? null : $customerName;
    }

    /**
     * Build reason object from chargeback data
     *
     * @param array $chargebackData
     * @return []
     */
    public function makeReason(array $chargebackData)
    {
        $reason = [];

        if (empty($chargebackData['paymentProcessor']) === false) {
            $reason['processorName'] = $chargebackData['paymentProcessor'];
        }

        if (empty($chargebackData['reasonCode']) === false) {
            $reason['processorReasonCode'] = $chargebackData['reasonCode'];
        }

        if (empty($chargebackData['reasonDescription']) === false) {
            $reason['processorReasonDescription'] = $chargebackData['reasonDescription'];
        }

        return $reason;
    }
}
