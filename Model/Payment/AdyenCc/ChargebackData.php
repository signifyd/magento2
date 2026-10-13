<?php

namespace Signifyd\Connect\Model\Payment\AdyenCc;

use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magento\Framework\Serialize\Serializer\Serialize as PhpSerializer;
use Signifyd\Connect\Logger\Logger;

class ChargebackData
{
    /**
     * Payment processor reported for a chargeback coming from an Adyen dispute.
     */
    public const PAYMENT_PROCESSOR = 'ADYEN';

    /**
     * Chargeback stage reported for each Adyen notification event code. Chargeback creation only
     * accepts RETRIEVAL and CHARGEBACK, the later stages are reported by the Chargeback Stage
     * endpoint instead, so a second chargeback is still created as a CHARGEBACK.
     */
    public const CHARGEBACK_STAGE_MAP = [
        'CHARGEBACK' => 'CHARGEBACK',
        'SECOND_CHARGEBACK' => 'CHARGEBACK',
    ];

    /**
     * Chargeback stage reported when the event code has no mapping.
     */
    public const DEFAULT_CHARGEBACK_STAGE = 'CHARGEBACK';

    /**
     * Payment network for each Adyen payment method variant.
     */
    public const PAYMENT_NETWORK_MAP = [
        'visa' => 'VISA',
        'visacredit' => 'VISA',
        'visadebit' => 'VISA',
        'visadankort' => 'VISA',
        'electron' => 'VISA',
        'mc' => 'MASTERCARD',
        'mccredit' => 'MASTERCARD',
        'mcdebit' => 'MASTERCARD',
        'maestro' => 'MAESTRO',
        'amex' => 'AMERICAN_EXPRESS',
        'discover' => 'DISCOVER',
        'diners' => 'DINERS_CLUB',
        'jcb' => 'JCB',
        'cup' => 'UNIONPAY',
        'elo' => 'ELO',
        'hipercard' => 'HIPERCARD',
        'cartebancaire' => 'CARTES_BANCAIRES',
        'interac' => 'INTERAC',
    ];

    /**
     * @var Logger
     */
    public $logger;

    /**
     * @var JsonSerializer
     */
    public $jsonSerializer;

    /**
     * @var PhpSerializer
     */
    public $phpSerializer;

    /**
     * @var ObjectManagerInterface
     */
    public $objectManagerInterface;

    /**
     * ChargebackData construct.
     *
     * @param Logger $logger
     * @param JsonSerializer $jsonSerializer
     * @param PhpSerializer $phpSerializer
     * @param ObjectManagerInterface $objectManagerInterface
     */
    public function __construct(
        Logger $logger,
        JsonSerializer $jsonSerializer,
        PhpSerializer $phpSerializer,
        ObjectManagerInterface $objectManagerInterface
    ) {
        $this->logger = $logger;
        $this->jsonSerializer = $jsonSerializer;
        $this->phpSerializer = $phpSerializer;
        $this->objectManagerInterface = $objectManagerInterface;
    }

    /**
     * Build the chargeback data out of an Adyen notification.
     *
     * Returns the payment agnostic array Signifyd\Connect\Model\Api\Chargeback expects, so that
     * the API object itself stays generic. Any other event code returns null, so that credit
     * memos created by refunds keep the default behavior.
     *
     * @param \Adyen\Payment\Model\Notification $notification
     * @return array|null
     */
    public function __invoke($notification)
    {
        try {
            if (is_object($notification) === false) {
                return null;
            }

            $eventCode = strtoupper((string) $notification->getEventCode());

            if (in_array($eventCode, RecordReturnChecker::CHARGEBACK_EVENT_CODES, true) === false) {
                return null;
            }

            $this->logger->debug(
                "Adyen {$eventCode} notification received for order " . $notification->getMerchantReference()
            );

            return $this->getChargebackData($notification);
        } catch (\Exception $e) {
            $this->logger->error('Failed to read Adyen dispute: ' . $e->getMessage());
            return null;
        } catch (\Error $e) {
            $this->logger->error('Failed to read Adyen dispute: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Build the chargeback data out of the notification and its additional data.
     *
     * The notification carries no cardholder details and no dispute fees, those are only
     * available on the Adyen settlement reports, so they are left for
     * Signifyd\Connect\Model\Api\ChargebackCreateRequest to fill in from the order.
     *
     * @param \Adyen\Payment\Model\Notification $notification
     * @return array
     */
    public function getChargebackData($notification)
    {
        $additionalData = $this->getAdditionalData($notification);
        $currency = strtoupper((string) $notification->getAmountCurrency());
        $chargebackId = $additionalData['disputePspReference'] ?? $notification->getPspreference();

        if (empty($chargebackId)) {
            return [];
        }

        $chargebackData = [];
        $chargebackData['chargebackId'] = (string) $chargebackId;
        $chargebackData['chargebackAmount'] = $this->toMajorUnits($notification->getAmountValue(), $currency);
        $chargebackData['chargebackCurrency'] = empty($currency) ? null : $currency;
        /**
         * Notifications are stored as they arrive and processed later on, so the date the
         * notification was created is the date Adyen reported the dispute. Stored with no time
         * zone information, on UTC.
         */
        $chargebackData['chargebackDate'] = $this->toIso8601($notification->getCreatedAt(), true);
        $chargebackData['disputeDeadlineAt'] = $this->toIso8601($additionalData['defensePeriodEndsAt'] ?? null);
        $chargebackData['chargebackStage'] = $this->getChargebackStage($notification->getEventCode());
        $chargebackData['paymentProcessor'] = self::PAYMENT_PROCESSOR;
        $chargebackData['paymentNetwork'] = $this->getPaymentNetwork($notification, $additionalData);
        $chargebackData['reasonCode'] = $this->getReasonCode($additionalData);
        $chargebackData['reasonDescription'] = $this->getReasonDescription($notification);

        return $chargebackData;
    }

    /**
     * Get the additional data of the notification, where the dispute details are reported.
     *
     * Adyen stores it already serialized, with the PHP serializer it wires on its own di.xml,
     * falling back to JSON so that an installation serializing it otherwise is also covered.
     *
     * @param \Adyen\Payment\Model\Notification $notification
     * @return array
     */
    public function getAdditionalData($notification)
    {
        $additionalData = $notification->getAdditionalData();

        if (empty($additionalData)) {
            return [];
        }

        if (is_array($additionalData)) {
            return $additionalData;
        }

        $additionalData = (string) $additionalData;

        try {
            $parsedData = $this->phpSerializer->unserialize($additionalData);
        } catch (\InvalidArgumentException $e) {
            try {
                $parsedData = $this->jsonSerializer->unserialize($additionalData);
            } catch (\InvalidArgumentException $e) {
                $this->logger->error('Failed to read Adyen notification additional data: ' . $e->getMessage());
                return [];
            }
        }

        return is_array($parsedData) ? $parsedData : [];
    }

    /**
     * Get the chargeback stage out of the notification event code.
     *
     * @param mixed $eventCode
     * @return string
     */
    public function getChargebackStage($eventCode)
    {
        $eventCode = strtoupper((string) $eventCode);

        return self::CHARGEBACK_STAGE_MAP[$eventCode] ?? self::DEFAULT_CHARGEBACK_STAGE;
    }

    /**
     * Get the payment network out of the payment method variant.
     *
     * @param \Adyen\Payment\Model\Notification $notification
     * @param array $additionalData
     * @return string|null
     */
    public function getPaymentNetwork($notification, array $additionalData)
    {
        $paymentMethodVariant = $additionalData['paymentMethodVariant'] ?? $notification->getPaymentMethod();

        if (empty($paymentMethodVariant)) {
            return null;
        }

        $paymentMethodVariant = strtolower((string) $paymentMethodVariant);

        return self::PAYMENT_NETWORK_MAP[$paymentMethodVariant] ?? strtoupper($paymentMethodVariant);
    }

    /**
     * Get the reason code the card network reported for the dispute.
     *
     * @param array $additionalData
     * @return string|null
     */
    public function getReasonCode(array $additionalData)
    {
        $reasonCode = $additionalData['chargebackReasonCode'] ?? null;

        return empty($reasonCode) ? null : (string) $reasonCode;
    }

    /**
     * Get the description Adyen reported for the dispute.
     *
     * Adyen leaves it on the reason field of the notification.
     *
     * @param \Adyen\Payment\Model\Notification $notification
     * @return string|null
     */
    public function getReasonDescription($notification)
    {
        $reason = trim((string) $notification->getReason());

        return empty($reason) ? null : $reason;
    }

    /**
     * Convert an Adyen amount, given on the minor unit of the currency, to the whole unit.
     *
     * Delegated to the Adyen helper so that the reported amount always matches the one the Adyen
     * module itself uses for the credit memo it creates for this same notification.
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

        $adyenDataHelper = $this->objectManagerInterface->create(\Adyen\Payment\Helper\Data::class);

        return (float) $adyenDataHelper->originalAmount((int) $amount, $currency);
    }

    /**
     * Convert an Adyen date to the ISO 8601 format the Signifyd API expects.
     *
     * @param mixed $date
     * @param bool $isUtcWithoutTimeZone Date held with no time zone information, as stored on the database
     * @return string|null
     */
    public function toIso8601($date, $isUtcWithoutTimeZone = false)
    {
        if (empty($date)) {
            return null;
        }

        $timestamp = strtotime((string) $date . ($isUtcWithoutTimeZone ? ' UTC' : ''));

        if (empty($timestamp)) {
            return null;
        }

        return gmdate('c', $timestamp);
    }
}
