<?php

namespace Signifyd\Connect\Plugin\StripeIntegration\Payments\Helper;

use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\Payment\Stripe\Payments\RecordReturnChecker;
use Signifyd\Connect\Model\Registry;
use StripeIntegration\Payments\Helper\Webhooks as StripeWebhooks;

class Webhooks
{
    /**
     * Stripe webhook event type that makes the Stripe module create a credit memo on its own
     * when the dispute is lost.
     */
    public const DISPUTE_CLOSED_EVENT_TYPE = 'charge.dispute.closed';

    /**
     * Payment processor reported for a chargeback coming from a Stripe dispute.
     */
    public const PAYMENT_PROCESSOR = 'STRIPE';

    /**
     * Chargeback stage reported when the dispute carries no case type. Chargeback creation only
     * accepts RETRIEVAL and CHARGEBACK, the later stages are reported by the Chargeback Stage
     * endpoint instead.
     */
    public const DEFAULT_CHARGEBACK_STAGE = 'CHARGEBACK';

    /**
     * Chargeback stage for each Stripe dispute case type. A compliance dispute is created as a
     * chargeback, it only moves to pre arbitration later on.
     */
    public const CHARGEBACK_STAGE_MAP = [
        'chargeback' => 'CHARGEBACK',
        'inquiry' => 'RETRIEVAL',
        'compliance' => 'CHARGEBACK',
    ];

    /**
     * Payment network for each Stripe card brand.
     */
    public const PAYMENT_NETWORK_MAP = [
        'visa' => 'VISA',
        'mastercard' => 'MASTERCARD',
        'amex' => 'AMERICAN_EXPRESS',
        'american_express' => 'AMERICAN_EXPRESS',
        'discover' => 'DISCOVER',
        'diners' => 'DINERS_CLUB',
        'jcb' => 'JCB',
        'unionpay' => 'UNIONPAY',
        'eftpos_au' => 'EFTPOS',
        'interac' => 'INTERAC',
        'cartes_bancaires' => 'CARTES_BANCAIRES',
    ];

    /**
     * Human readable description for each Stripe dispute reason.
     */
    public const REASON_DESCRIPTION_MAP = [
        'bank_cannot_process' => 'Bank cannot process',
        'check_returned' => 'Check returned',
        'credit_not_processed' => 'Credit not processed',
        'customer_initiated' => 'Customer initiated',
        'debit_not_authorized' => 'Debit not authorized',
        'duplicate' => 'Duplicate',
        'fraudulent' => 'Fraudulent',
        'general' => 'General',
        'incorrect_account_details' => 'Incorrect account details',
        'insufficient_funds' => 'Insufficient funds',
        'noncompliant' => 'Noncompliant',
        'product_not_received' => 'Product not received',
        'product_unacceptable' => 'Product unacceptable',
        'subscription_canceled' => 'Subscription canceled',
        'unrecognized' => 'Unrecognized',
    ];

    /**
     * Stripe reports amounts on the smallest unit of the currency, which is the whole unit for
     * these currencies.
     */
    public const ZERO_DECIMAL_CURRENCIES = [
        'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA',
        'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF',
    ];

    /**
     * Currencies where the smallest unit is one thousandth of the whole unit.
     */
    public const THREE_DECIMAL_CURRENCIES = ['BHD', 'JOD', 'KWD', 'OMR', 'TND'];

    /**
     * @var Logger
     */
    public $logger;

    /**
     * @var Registry
     */
    public $registry;

    /**
     * @var HttpRequest
     */
    public $request;

    /**
     * @var JsonSerializer
     */
    public $jsonSerializer;

    /**
     * Webhooks constructor.
     *
     * @param Logger $logger
     * @param Registry $registry
     * @param HttpRequest $request
     * @param JsonSerializer $jsonSerializer
     */
    public function __construct(
        Logger $logger,
        Registry $registry,
        HttpRequest $request,
        JsonSerializer $jsonSerializer
    ) {
        $this->logger = $logger;
        $this->registry = $registry;
        $this->request = $request;
        $this->jsonSerializer = $jsonSerializer;
    }

    /**
     * Plugin around dispatch event method.
     *
     * Makes the status of a closing Stripe dispute available to
     * Signifyd\Connect\Model\Payment\Stripe\Payments\RecordReturnChecker, which suppresses the
     * Record Return, and leaves the chargeback data of that same dispute on the registry, so that
     * Signifyd\Connect\Model\Api\Core\Client reports it instead. The credit memo the Stripe module
     * creates for a lost dispute, and therefore both of them, happens entirely inside $proceed.
     *
     * @param StripeWebhooks $subject
     * @param callable $proceed
     * @param mixed $stdEvent
     * @param bool $processMoreThanOnce
     * @return mixed
     */
    public function aroundDispatchEvent(
        StripeWebhooks $subject,
        callable $proceed,
        $stdEvent = null,
        $processMoreThanOnce = false
    ) {
        $dispute = $this->getDispute($stdEvent);

        $this->registry->setData(
            RecordReturnChecker::DISPUTE_STATUS_REGISTRY_KEY,
            $dispute === null ? null : ($dispute['status'] ?? null)
        );
        $this->registry->setData(
            Registry::CHARGEBACK_DATA_REGISTRY_KEY,
            $dispute === null ? null : $this->getChargebackData($dispute)
        );

        try {
            return $proceed($stdEvent, $processMoreThanOnce);
        } finally {
            $this->registry->setData(RecordReturnChecker::DISPUTE_STATUS_REGISTRY_KEY);
            $this->registry->setData(Registry::CHARGEBACK_DATA_REGISTRY_KEY);
        }
    }

    /**
     * Get the dispute object from the event being dispatched.
     *
     * Returns null for any other event type, so that credit memos created by refunds keep the
     * default behavior. The event comes as an argument on the cron retry and CLI paths, and on
     * the request body when the Stripe webhook controller is the caller.
     *
     * @param mixed $stdEvent
     * @return array|null
     */
    public function getDispute($stdEvent)
    {
        try {
            if (isset($stdEvent)) {
                $event = $this->jsonSerializer->unserialize($this->jsonSerializer->serialize($stdEvent));
            } else {
                $event = $this->jsonSerializer->unserialize((string) $this->request->getContent());
            }

            if (is_array($event) === false ||
                isset($event['type']) === false ||
                $event['type'] !== self::DISPUTE_CLOSED_EVENT_TYPE
            ) {
                return null;
            }

            $dispute = $event['data']['object'] ?? null;

            if (is_array($dispute) === false) {
                return null;
            }

            $this->logger->debug(
                "Stripe dispute closed event received with status " . ($dispute['status'] ?? '')
            );

            return $dispute;
        } catch (\Exception $e) {
            $this->logger->error('Failed to read Stripe dispute: ' . $e->getMessage());
            return null;
        } catch (\Error $e) {
            $this->logger->error('Failed to read Stripe dispute: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Build the chargeback data out of the Stripe dispute.
     *
     * Returns the payment agnostic array Signifyd\Connect\Model\Api\Chargeback expects, so that
     * the API object itself stays generic.
     *
     * @param array $dispute
     * @return array
     */
    public function getChargebackData(array $dispute)
    {
        if (empty($dispute['id'])) {
            return [];
        }

        $card = is_array($dispute['payment_method_details']['card'] ?? null)
            ? $dispute['payment_method_details']['card']
            : [];
        $evidence = is_array($dispute['evidence'] ?? null) ? $dispute['evidence'] : [];
        $currency = strtoupper((string) ($dispute['currency'] ?? ''));
        $fees = $this->getChargebackFees($dispute, $currency);

        $chargebackData = [];
        $chargebackData['chargebackId'] = (string) $dispute['id'];
        $chargebackData['chargebackAmount'] = $this->toMajorUnits($dispute['amount'] ?? null, $currency);
        $chargebackData['chargebackCurrency'] = empty($currency) ? null : $currency;
        $chargebackData['chargebackDate'] = $this->toIso8601($dispute['created'] ?? null);
        $chargebackData['disputeDeadlineAt'] = $this->toIso8601($dispute['evidence_details']['due_by'] ?? null);
        $chargebackData['chargebackStage'] = $this->getChargebackStage($card);
        $chargebackData['paymentProcessor'] = self::PAYMENT_PROCESSOR;
        $chargebackData['paymentNetwork'] = $this->getPaymentNetwork($card);
        $chargebackData['reasonCode'] = $this->getReasonCode($dispute, $card);
        $chargebackData['reasonDescription'] = $this->getReasonDescription($dispute);
        $chargebackData['cardholderName'] = $evidence['customer_name'] ?? null;
        $chargebackData['cardholderEmail'] = $evidence['customer_email_address'] ?? null;
        $chargebackData['chargebackFeeAmount'] = $fees['amount'];
        $chargebackData['chargebackFeeCurrency'] = $fees['currency'];

        return $chargebackData;
    }

    /**
     * Get the chargeback stage out of the dispute case type.
     *
     * @param array $card
     * @return string
     */
    public function getChargebackStage(array $card)
    {
        $caseType = strtolower((string) ($card['case_type'] ?? ''));

        return self::CHARGEBACK_STAGE_MAP[$caseType] ?? self::DEFAULT_CHARGEBACK_STAGE;
    }

    /**
     * Get the payment network out of the card brand.
     *
     * @param array $card
     * @return string|null
     */
    public function getPaymentNetwork(array $card)
    {
        $brand = $card['network'] ?? ($card['brand'] ?? null);

        if (empty($brand)) {
            return null;
        }

        $brand = strtolower((string) $brand);

        return self::PAYMENT_NETWORK_MAP[$brand] ?? strtoupper($brand);
    }

    /**
     * Get the reason code the card network reported for the dispute.
     *
     * The network reason code is the one issuers use, so it is preferred over the Stripe reason.
     *
     * @param array $dispute
     * @param array $card
     * @return string|null
     */
    public function getReasonCode(array $dispute, array $card)
    {
        $reasonCode = $card['network_reason_code']
            ?? ($dispute['network_reason_code'] ?? ($dispute['reason'] ?? null));

        return empty($reasonCode) ? null : (string) $reasonCode;
    }

    /**
     * Get a human readable description for the Stripe dispute reason.
     *
     * @param array $dispute
     * @return string|null
     */
    public function getReasonDescription(array $dispute)
    {
        $reason = strtolower((string) ($dispute['reason'] ?? ''));

        if (empty($reason)) {
            return null;
        }

        return self::REASON_DESCRIPTION_MAP[$reason] ?? ucfirst(str_replace('_', ' ', $reason));
    }

    /**
     * Get the fees Stripe charged for the dispute out of its balance transactions.
     *
     * @param array $dispute
     * @param string $currency
     * @return array
     */
    public function getChargebackFees(array $dispute, $currency)
    {
        $balanceTransactions = $dispute['balance_transactions'] ?? null;

        if (is_array($balanceTransactions) === false) {
            return ['amount' => null, 'currency' => null];
        }

        $feeCurrency = null;
        $feeTotal = 0;

        foreach ($balanceTransactions as $balanceTransaction) {
            if (is_array($balanceTransaction) === false ||
                is_numeric($balanceTransaction['fee'] ?? null) === false
            ) {
                continue;
            }

            $fee = (int) $balanceTransaction['fee'];

            if ($fee <= 0) {
                continue;
            }

            $feeCurrency = $feeCurrency ?? strtoupper((string) ($balanceTransaction['currency'] ?? $currency));
            $feeTotal += $fee;
        }

        if ($feeTotal === 0) {
            return ['amount' => null, 'currency' => null];
        }

        return [
            'amount' => $this->toMajorUnits($feeTotal, $feeCurrency),
            'currency' => $feeCurrency,
        ];
    }

    /**
     * Convert a Stripe amount, given on the smallest unit of the currency, to the whole unit.
     *
     * @param mixed $amount
     * @param string $currency
     * @return float|null
     */
    public function toMajorUnits($amount, $currency)
    {
        if (is_numeric($amount) === false) {
            return null;
        }

        $currency = strtoupper((string) $currency);

        if (in_array($currency, self::ZERO_DECIMAL_CURRENCIES, true)) {
            return (float) $amount;
        }

        if (in_array($currency, self::THREE_DECIMAL_CURRENCIES, true)) {
            return round((float) $amount / 1000, 3);
        }

        return round((float) $amount / 100, 2);
    }

    /**
     * Convert a Stripe timestamp to the ISO 8601 format the Signifyd API expects.
     *
     * @param mixed $timestamp
     * @return string|null
     */
    public function toIso8601($timestamp)
    {
        if (is_numeric($timestamp) === false || (int) $timestamp <= 0) {
            return null;
        }

        return gmdate('c', (int) $timestamp);
    }
}
