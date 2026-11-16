<?php

namespace Signifyd\Connect\Plugin\StripeIntegration\Payments\Model\Stripe\Event;

use Magento\Framework\ObjectManagerInterface;
use Magento\Sales\Model\Order;
use Signifyd\Connect\Helper\PaymentStatusHelper;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\Api\CaseData\PreAuth\ProcessTransactionFactory;
use Signifyd\Connect\Model\Api\GatewayStatusCode;
use Signifyd\Connect\Model\Payment\Base\GatewayErrorCodeMapper;
use StripeIntegration\Payments\Model\Stripe\Event\PaymentIntentPaymentFailed as StripePaymentIntentPaymentFailed;

/**
 * Stripe tells us the payment of an already placed order was refused through the
 * payment_intent.payment_failed webhook. That is the gateway verdict which turns the
 * PENDING transaction posted at placement into a FAILURE.
 */
class PaymentIntentPaymentFailed
{
    /**
     * @var Logger
     */
    public $logger;

    /**
     * @var PaymentStatusHelper
     */
    public $paymentStatusHelper;

    /**
     * @var GatewayErrorCodeMapper
     */
    public $gatewayErrorCodeMapper;

    /**
     * @var ProcessTransactionFactory
     */
    public $processTransactionFactory;

    /**
     * @var ObjectManagerInterface
     */
    public $objectManagerInterface;

    /**
     * PaymentIntentPaymentFailed constructor.
     *
     * @param Logger $logger
     * @param PaymentStatusHelper $paymentStatusHelper
     * @param GatewayErrorCodeMapper $gatewayErrorCodeMapper
     * @param ProcessTransactionFactory $processTransactionFactory
     * @param ObjectManagerInterface $objectManagerInterface
     */
    public function __construct(
        Logger $logger,
        PaymentStatusHelper $paymentStatusHelper,
        GatewayErrorCodeMapper $gatewayErrorCodeMapper,
        ProcessTransactionFactory $processTransactionFactory,
        ObjectManagerInterface $objectManagerInterface
    ) {
        $this->logger = $logger;
        $this->paymentStatusHelper = $paymentStatusHelper;
        $this->gatewayErrorCodeMapper = $gatewayErrorCodeMapper;
        $this->processTransactionFactory = $processTransactionFactory;
        $this->objectManagerInterface = $objectManagerInterface;
    }

    /**
     * Plugin after on process method.
     *
     * @param StripePaymentIntentPaymentFailed $subject
     * @param mixed $result
     * @param mixed $arrEvent
     * @param mixed $object
     * @return mixed
     */
    public function afterProcess(
        StripePaymentIntentPaymentFailed $subject,
        $result,
        $arrEvent = null,
        $object = null
    ) {
        try {
            $webhooksHelper = $this->objectManagerInterface->create(
                \StripeIntegration\Payments\Helper\Webhooks::class
            );
            $orders = $webhooksHelper->loadOrderFromEvent($arrEvent, true);

            if (is_array($orders) === false) {
                $orders = [$orders];
            }

            $lastError = $this->getLastError($object);
            $errorCode = ($this->gatewayErrorCodeMapper)(
                $lastError['decline_code'] ?? null,
                $lastError['code'] ?? null
            );
            $statusMessage = $lastError['message'] ?? null;

            foreach ($orders as $order) {
                if ($order instanceof Order === false) {
                    continue;
                }

                // A failed attempt does not undo a payment that already went through
                if ($this->paymentStatusHelper->hasPaymentRegistered($order)) {
                    continue;
                }

                // The Stripe module cancels abandoned/expired payments while processing this
                // same event, and the cancellation flow already posted the final transaction
                // (CANCELLED). A verdict recorded here would overwrite it with FAILURE
                if ($this->paymentStatusHelper->isOrderCanceled($order)) {
                    continue;
                }

                $recorded = $this->paymentStatusHelper->recordGatewayStatus(
                    $order,
                    GatewayStatusCode::FAILURE,
                    $errorCode,
                    $statusMessage
                );

                if ($recorded === false) {
                    continue;
                }

                ($this->processTransactionFactory->create())($order);
            }
        } catch (\Exception $ex) {
            $this->logger->error($ex->getMessage());
        } catch (\Error $ex) {
            $this->logger->error($ex->getMessage());
        }

        return $result;
    }

    /**
     * Extracts the error reported by Stripe from the payment intent of the event.
     *
     * @param mixed $object
     * @return array
     */
    public function getLastError($object)
    {
        if (is_array($object) === false) {
            return [];
        }

        if (empty($object['last_payment_error']) === false) {
            return (array) $object['last_payment_error'];
        }

        if (empty($object['last_setup_error']) === false) {
            return (array) $object['last_setup_error'];
        }

        return [];
    }
}
