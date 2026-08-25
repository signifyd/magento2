<?php

namespace Signifyd\Connect\Model\Api;

use Magento\Framework\Stdlib\DateTime\DateTimeFactory;
use Magento\Sales\Model\Order;

class ChargebackCreateRequest
{
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
        $now = date('c', strtotime($dateTime->gmtDate()));

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

        $createRequest['issuerReportedDate'] = (
            isset($chargebackData['chargebackDate'])
                ? date('c', strtotime($chargebackData['chargebackDate']))
                : $now
        );

        if (empty($chargebackData['disputeDeadlineAt']) === false) {
            $createRequest['dueDate'] = (date('c', strtotime($chargebackData['disputeDeadlineAt'])));
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

        if (empty($chargebackData['cardholderEmail']) === false) {
            $createRequest['cardholderEmail'] = $chargebackData['cardholderEmail'];
        }

        if (empty($chargebackData['cardholderName']) === false) {
            $createRequest['cardholderName'] = $chargebackData['cardholderName'];
        }

        if (empty($chargebackData['chargebackFeeAmount']) === false) {
            $chargebackFees = [];
            $chargebackFees['amount'] = (float) $chargebackData['chargebackFeeAmount'];
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
