<?php
/**
 * Copyright 2017 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Signifyd\Connect\Helper;

use Magento\Sales\Model\Order;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\CasedataFactory;
use Signifyd\Connect\Model\ResourceModel\Casedata as CasedataResourceModel;

/**
 * Answers, in a gateway agnostic way, whether the payment of an order has actually concluded.
 *
 * Redirect and 3DS flows (Stripe, Adyen, PayPal, ...) save the Magento order before the payment
 * loop ends, so the order alone is not enough to tell an approved purchase from one that is still
 * waiting for the customer, was refused by the gateway or was never paid at all.
 */
class PaymentStatusHelper
{
    /**
     * Case entry holding the last status code reported by the gateway for the order
     */
    public const GATEWAY_STATUS_CODE_ENTRY = 'gateway_status_code';

    /**
     * Case entry holding the last error code reported by the gateway for the order
     */
    public const GATEWAY_ERROR_CODE_ENTRY = 'gateway_error_code';

    /**
     * Case entry holding the last status message reported by the gateway for the order
     */
    public const GATEWAY_STATUS_MESSAGE_ENTRY = 'gateway_status_message';

    /**
     * Gateway verdicts that describe a payment that will never conclude. Once one of them is
     * recorded for an order it is kept until the payment actually succeeds.
     *
     * @var array
     */
    public const TERMINAL_STATUS_CODES = ['FAILURE', 'ERROR', 'CANCELLED', 'EXPIRED', 'SOFTDECLINE'];

    /**
     * @var Logger
     */
    public $logger;

    /**
     * @var CasedataFactory
     */
    public $casedataFactory;

    /**
     * @var CasedataResourceModel
     */
    public $casedataResourceModel;

    /**
     * PaymentStatusHelper construct.
     *
     * @param Logger $logger
     * @param CasedataFactory $casedataFactory
     * @param CasedataResourceModel $casedataResourceModel
     */
    public function __construct(
        Logger $logger,
        CasedataFactory $casedataFactory,
        CasedataResourceModel $casedataResourceModel
    ) {
        $this->logger = $logger;
        $this->casedataFactory = $casedataFactory;
        $this->casedataResourceModel = $casedataResourceModel;
    }

    /**
     * Whether the payment loop has not concluded yet.
     *
     * For instance, the customer is on a 3DS challenge or on the gateway hosted page.
     *
     * @param Order $order
     * @return bool
     */
    public function isPaymentPending(Order $order)
    {
        if ($order->getState() === Order::STATE_PENDING_PAYMENT) {
            return true;
        }

        $payment = $order->getPayment();

        if (isset($payment) === false) {
            return false;
        }

        // Magento flag, set by the gateway during the current request and not persisted
        if ($payment->getIsTransactionPending()) {
            return true;
        }

        // Persisted counterpart, written by gateways that place the order before authorizing it
        return (bool) $payment->getAdditionalInformation('is_transaction_pending');
    }

    /**
     * Whether the order has been canceled, or is being canceled on the current request.
     *
     * @param Order $order
     * @return bool
     */
    public function isOrderCanceled(Order $order)
    {
        return $order->getState() === Order::STATE_CANCELED || $order->isCanceled();
    }

    /**
     * Whether there is positive evidence that the payment of this order never concluded.
     *
     * Absence of evidence is not evidence: offline payment methods sit on new/pending with no
     * authorization of their own and must keep being treated as they always were, so this only
     * answers true when something actually says the payment loop did not finish.
     *
     * @param Order $order
     * @return bool
     */
    public function isPaymentUnresolved(Order $order)
    {
        if (isset($order) === false || $order->getPayment() === null) {
            return false;
        }

        // The gateway is still waiting on the customer, or on itself
        if ($this->isPaymentPending($order)) {
            return true;
        }

        // Canceled straight out of pending_payment: whatever the gateway wrote on the payment when
        // the order was placed was never confirmed by an authorization
        if ($this->isOrderCanceled($order) &&
            $order->getOrigData('state') === Order::STATE_PENDING_PAYMENT
        ) {
            return true;
        }

        // The gateway reported a failure and no payment was ever registered for this order
        $reportedStatusCode = $this->getGatewayStatusCode($order);

        if (isset($reportedStatusCode) && $this->hasPaymentRegistered($order) === false) {
            return true;
        }

        return false;
    }

    /**
     * Whether an authorization or a capture was registered for this order.
     *
     * @param Order $order
     * @return bool
     */
    public function hasPaymentRegistered(Order $order)
    {
        $payment = $order->getPayment();

        if (isset($payment) === false) {
            return false;
        }

        if ((float) $order->getBaseTotalPaid() > 0 || (float) $payment->getBaseAmountPaid() > 0) {
            return true;
        }

        if ((float) $payment->getBaseAmountAuthorized() > 0) {
            return true;
        }

        // An order only reaches these states after the payment method approved the payment
        return in_array(
            $order->getState(),
            [Order::STATE_PROCESSING, Order::STATE_COMPLETE, Order::STATE_CLOSED],
            true
        );
    }

    /**
     * Last terminal status code reported by the gateway for this order, if any.
     *
     * @param Order $order
     * @return string|null
     */
    public function getGatewayStatusCode(Order $order)
    {
        $statusCode = $this->getCaseEntry($order, self::GATEWAY_STATUS_CODE_ENTRY);

        if (in_array($statusCode, self::TERMINAL_STATUS_CODES, true) === false) {
            return null;
        }

        return $statusCode;
    }

    /**
     * Last error code reported by the gateway for this order, if any.
     *
     * @param Order $order
     * @return string|null
     */
    public function getGatewayErrorCode(Order $order)
    {
        return $this->getCaseEntry($order, self::GATEWAY_ERROR_CODE_ENTRY);
    }

    /**
     * Last status message reported by the gateway for this order, if any.
     *
     * @param Order $order
     * @return string|null
     */
    public function getGatewayStatusMessage(Order $order)
    {
        return $this->getCaseEntry($order, self::GATEWAY_STATUS_MESSAGE_ENTRY);
    }

    /**
     * Records the verdict the gateway gave for the payment of this order.
     *
     * The next transaction posted to Signifyd for the order carries it.
     *
     * @param Order $order
     * @param string $statusCode
     * @param string|null $errorCode
     * @param string|null $statusMessage
     * @return bool
     */
    public function recordGatewayStatus(Order $order, $statusCode, $errorCode = null, $statusMessage = null)
    {
        if (in_array($statusCode, self::TERMINAL_STATUS_CODES, true) === false) {
            return false;
        }

        /** @var \Signifyd\Connect\Model\Casedata $case */
        $case = $this->casedataFactory->create();
        $this->casedataResourceModel->load($case, $order->getId(), 'order_id');

        if ($case->isEmpty()) {
            return false;
        }

        if ($case->getEntries(self::GATEWAY_STATUS_CODE_ENTRY) === $statusCode &&
            $case->getEntries(self::GATEWAY_ERROR_CODE_ENTRY) === $errorCode
        ) {
            return true;
        }

        $case->setEntries(self::GATEWAY_STATUS_CODE_ENTRY, $statusCode);

        if (isset($errorCode)) {
            $case->setEntries(self::GATEWAY_ERROR_CODE_ENTRY, $errorCode);
        }

        if (isset($statusMessage)) {
            $case->setEntries(self::GATEWAY_STATUS_MESSAGE_ENTRY, $statusMessage);
        }

        if ($this->casedataResourceModel->isCaseLocked($case)) {
            $message = "Gateway status {$statusCode} for order {$order->getIncrementId()} not stored: case is locked";
            $this->logger->debug($message, ['entity' => $order]);
            return false;
        }

        $this->casedataResourceModel->save($case);

        $this->logger->info(
            "Gateway reported {$statusCode} for order {$order->getIncrementId()}",
            ['entity' => $order]
        );

        return true;
    }

    /**
     * Reads one entry from the case of the given order.
     *
     * @param Order $order
     * @param string $entry
     * @return mixed
     */
    public function getCaseEntry(Order $order, $entry)
    {
        $orderId = $order->getId();

        if (empty($orderId)) {
            return null;
        }

        /** @var \Signifyd\Connect\Model\Casedata $case */
        $case = $this->casedataFactory->create();
        $this->casedataResourceModel->load($case, $orderId, 'order_id');

        if ($case->isEmpty()) {
            return null;
        }

        return $case->getEntries($entry);
    }
}
