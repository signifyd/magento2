<?php

namespace Signifyd\Connect\Model\Payment\Braintree;

use Signifyd\Connect\Logger\Logger;

class ChargebackData
{
    /**
     * Braintree webhook kind sent when a dispute is opened, the only one a chargeback is created for.
     * The later kinds report the outcome of that same dispute instead.
     */
    public const DISPUTE_OPENED_KIND = 'dispute_opened';

    /**
     * Payment processor reported for a chargeback coming from a Braintree dispute.
     */
    public const PAYMENT_PROCESSOR = 'BRAINTREE';

    /**
     * Chargeback stage for each Braintree dispute kind. Chargeback creation only accepts RETRIEVAL
     * and CHARGEBACK, the later stages are reported by the Chargeback Stage endpoint instead, so a
     * pre arbitration is still created as a CHARGEBACK.
     */
    public const CHARGEBACK_STAGE_MAP = [
        'chargeback' => 'CHARGEBACK',
        'pre_arbitration' => 'CHARGEBACK',
        'retrieval' => 'RETRIEVAL',
    ];

    /**
     * Chargeback stage reported when the dispute kind has no mapping.
     */
    public const DEFAULT_CHARGEBACK_STAGE = 'CHARGEBACK';

    /**
     * Payment network for each Braintree card type.
     */
    public const PAYMENT_NETWORK_MAP = [
        'visa' => 'VISA',
        'mastercard' => 'MASTERCARD',
        'american express' => 'AMERICAN_EXPRESS',
        'discover' => 'DISCOVER',
        'diners club' => 'DINERS_CLUB',
        'jcb' => 'JCB',
        'maestro' => 'MAESTRO',
        'uk maestro' => 'MAESTRO',
        'china unionpay' => 'UNIONPAY',
        'elo' => 'ELO',
    ];

    /**
     * @var Logger
     */
    public $logger;

    /**
     * ChargebackData construct.
     *
     * @param Logger $logger
     */
    public function __construct(
        Logger $logger
    ) {
        $this->logger = $logger;
    }

    /**
     * Build the chargeback data out of a Braintree webhook notification.
     *
     * Returns the payment agnostic array Signifyd\Connect\Model\Api\Chargeback expects, so that
     * the API object itself stays generic. Any other webhook kind returns null.
     *
     * @param \Braintree\WebhookNotification $notification
     * @return array|null
     */
    public function __invoke($notification)
    {
        try {
            $dispute = $this->getDispute($notification);

            if ($dispute === null) {
                return null;
            }

            $this->logger->debug(
                'Braintree dispute opened notification received for transaction ' . $this->getTransactionId($dispute)
            );

            return $this->getChargebackData($dispute);
        } catch (\Exception $e) {
            $this->logger->error('Failed to read Braintree dispute: ' . $e->getMessage());
            return null;
        } catch (\Error $e) {
            $this->logger->error('Failed to read Braintree dispute: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get the dispute out of a dispute opened notification.
     *
     * The Braintree SDK raises a notice for any attribute missing from the payload, so every
     * attribute is checked with isset before it is read.
     *
     * @param \Braintree\WebhookNotification $notification
     * @return \Braintree\Dispute|null
     */
    public function getDispute($notification)
    {
        if (is_object($notification) === false ||
            isset($notification->kind) === false ||
            $notification->kind !== self::DISPUTE_OPENED_KIND ||
            isset($notification->dispute) === false
        ) {
            return null;
        }

        return $notification->dispute;
    }

    /**
     * Get the id of the disputed transaction, the one the order is found by.
     *
     * @param \Braintree\Dispute $dispute
     * @return string|null
     */
    public function getTransactionId($dispute)
    {
        if (isset($dispute->transaction) === false || isset($dispute->transaction->id) === false) {
            return null;
        }

        return (string) $dispute->transaction->id;
    }

    /**
     * Build the chargeback data out of the dispute.
     *
     * The dispute carries no cardholder details and no dispute fees, so they are left for
     * Signifyd\Connect\Model\Api\ChargebackCreateRequest to fill in from the order.
     *
     * @param \Braintree\Dispute $dispute
     * @return array
     */
    public function getChargebackData($dispute)
    {
        if (isset($dispute->id) === false || empty($dispute->id)) {
            return [];
        }

        $currency = isset($dispute->currencyIsoCode) ? strtoupper((string) $dispute->currencyIsoCode) : '';

        $chargebackData = [];
        $chargebackData['chargebackId'] = (string) $dispute->id;
        $chargebackData['chargebackAmount'] = $this->getChargebackAmount($dispute);
        $chargebackData['chargebackCurrency'] = empty($currency) ? null : $currency;
        $chargebackData['chargebackDate'] = $this->toIso8601($dispute->receivedDate ?? null);
        $chargebackData['disputeDeadlineAt'] = $this->toIso8601($dispute->replyByDate ?? null);
        $chargebackData['chargebackStage'] = $this->getChargebackStage($dispute);
        $chargebackData['paymentProcessor'] = self::PAYMENT_PROCESSOR;
        $chargebackData['paymentNetwork'] = $this->getPaymentNetwork($dispute);
        $chargebackData['reasonCode'] = $this->getReasonCode($dispute);
        $chargebackData['reasonDescription'] = $this->getReasonDescription($dispute);

        return $chargebackData;
    }

    /**
     * Get the disputed amount, falling back to the amount of the dispute.
     *
     * @param \Braintree\Dispute $dispute
     * @return float|null
     */
    public function getChargebackAmount($dispute)
    {
        $amount = $dispute->amountDisputed ?? $dispute->amount ?? null;

        return is_numeric($amount) ? (float) $amount : null;
    }

    /**
     * Get the chargeback stage out of the dispute kind.
     *
     * @param \Braintree\Dispute $dispute
     * @return string
     */
    public function getChargebackStage($dispute)
    {
        $kind = strtolower((string) ($dispute->kind ?? ''));

        return self::CHARGEBACK_STAGE_MAP[$kind] ?? self::DEFAULT_CHARGEBACK_STAGE;
    }

    /**
     * Get the payment network out of the card type of the disputed transaction.
     *
     * @param \Braintree\Dispute $dispute
     * @return string|null
     */
    public function getPaymentNetwork($dispute)
    {
        if (isset($dispute->transaction) === false ||
            isset($dispute->transaction->paymentInstrumentSubtype) === false
        ) {
            return null;
        }

        $cardType = strtolower(trim((string) $dispute->transaction->paymentInstrumentSubtype));

        if (empty($cardType)) {
            return null;
        }

        return self::PAYMENT_NETWORK_MAP[$cardType] ?? strtoupper(str_replace(' ', '_', $cardType));
    }

    /**
     * Get the reason code the card network reported for the dispute.
     *
     * @param \Braintree\Dispute $dispute
     * @return string|null
     */
    public function getReasonCode($dispute)
    {
        $reasonCode = $dispute->reasonCode ?? null;

        return empty($reasonCode) ? null : (string) $reasonCode;
    }

    /**
     * Get the description of the dispute reason.
     *
     * Braintree reports the card network description when there is one, falling back to its own
     * generic reason otherwise.
     *
     * @param \Braintree\Dispute $dispute
     * @return string|null
     */
    public function getReasonDescription($dispute)
    {
        $reasonDescription = trim((string) ($dispute->reasonDescription ?? ''));

        if (empty($reasonDescription) === false) {
            return $reasonDescription;
        }

        $reason = trim((string) ($dispute->reason ?? ''));

        return empty($reason) ? null : ucfirst(str_replace('_', ' ', $reason));
    }

    /**
     * Convert a Braintree date to the ISO 8601 format the Signifyd API expects.
     *
     * @param mixed $date
     * @return string|null
     */
    public function toIso8601($date)
    {
        if ($date instanceof \DateTimeInterface) {
            return $date->format('c');
        }

        if (empty($date)) {
            return null;
        }

        $timestamp = strtotime((string) $date);

        if (empty($timestamp)) {
            return null;
        }

        return gmdate('c', $timestamp);
    }
}
