<?php

namespace Signifyd\Connect\Plugin\Magento\Sales\Model\Service;

use Closure;
use Error;
use Exception;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Gateway\Command\CommandException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Service\OrderService as MagentoOrderService;
use Magento\Sales\Api\Data\OrderInterface;
use Signifyd\Connect\Helper\PaymentStatusHelper;
use Signifyd\Connect\Model\Api\CaseData\PreAuth\ProcessTransactionFactory;
use Signifyd\Connect\Model\Api\GatewayStatusCode;
use Signifyd\Connect\Model\Payment\Base\GatewayErrorCodeMapper;
use Signifyd\Connect\Model\TransactionIntegration;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Core\Exceptions\ApiException;
use Signifyd\Core\Exceptions\InvalidClassException;

class OrderService
{
    /**
     * @var TransactionIntegration
     */
    public $transactionIntegration;

    /**
     * @var Logger
     */
    public $logger;

    /**
     * @var GatewayErrorCodeMapper
     */
    public $gatewayErrorCodeMapper;

    /**
     * @var PaymentStatusHelper
     */
    public $paymentStatusHelper;

    /**
     * @var ProcessTransactionFactory
     */
    public $processTransactionFactory;

    /**
     * OrderService constructor.
     *
     * @param TransactionIntegration $transactionIntegration
     * @param Logger $logger
     * @param GatewayErrorCodeMapper $gatewayErrorCodeMapper
     * @param PaymentStatusHelper $paymentStatusHelper
     * @param ProcessTransactionFactory $processTransactionFactory
     */
    public function __construct(
        TransactionIntegration $transactionIntegration,
        Logger $logger,
        GatewayErrorCodeMapper $gatewayErrorCodeMapper,
        PaymentStatusHelper $paymentStatusHelper,
        ProcessTransactionFactory $processTransactionFactory
    ) {
        $this->transactionIntegration = $transactionIntegration;
        $this->logger = $logger;
        $this->gatewayErrorCodeMapper = $gatewayErrorCodeMapper;
        $this->paymentStatusHelper = $paymentStatusHelper;
        $this->processTransactionFactory = $processTransactionFactory;
    }

    /**
     *  Around Place method responsible for mapping the error returned by the card.
     *
     * @param MagentoOrderService $subject
     * @param Closure $proceed
     * @param OrderInterface $order
     * @return mixed
     * @throws AlreadyExistsException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws ApiException
     * @throws InvalidClassException
     */
    public function aroundPlace(MagentoOrderService $subject, Closure $proceed, OrderInterface $order): mixed
    {
        try {
            return $proceed($order);
        } catch (CommandException $e) {
            try {
                $declineCode = $e->getCode();
                $errorMessage = $e->getMessage();

                $this->handleTransactionError($declineCode, $errorMessage, null, $order);
            } catch (Exception|Error $error) {
                $this->logger->warning(
                    'Failed to map command exception error details.',
                    [
                        'handling_error_exception' => $error,
                        'exception' => $e
                    ]
                );
            }

            throw $e;
        } catch (Exception $e) {
            try {
                $gatewayError = $this->getGatewayError($e);
                $declineCode = $gatewayError['decline_code'] ?? null;
                $errorCode = $gatewayError['code'] ?? null;
                $errorMessage = $gatewayError['message'] ?? null;

                $this->handleTransactionError($declineCode, $errorMessage, $errorCode, $order);
            } catch (Exception|Error $error) {
                $this->logger->warning(
                    'Failed to map exception error details.',
                    [
                        'handling_error_exception' => $error,
                        'exception' => $e
                    ]
                );
            }

            throw $e;
        }
    }

    /**
     * Reads the gateway error details out of an exception raised while placing the order.
     *
     * Stripe throws its own exceptions, which carry a \Stripe\ErrorObject. Other gateways wrap the
     * original error before rethrowing it, so the previous exception is inspected as well.
     *
     * @param \Throwable $exception
     * @return array
     */
    public function getGatewayError(\Throwable $exception)
    {
        $candidates = [$exception, $exception->getPrevious()];

        foreach ($candidates as $candidate) {
            if (isset($candidate) === false || method_exists($candidate, 'getError') === false) {
                continue;
            }

            $error = $candidate->getError();

            if (isset($error) === false) {
                continue;
            }

            return [
                'decline_code' => $error->decline_code ?? null,
                'code' => $error->code ?? null,
                'message' => $error->message ?? $candidate->getMessage(),
            ];
        }

        return [];
    }

    /**
     * Handle transaction error method.
     *
     * @param ?string $declineCode
     * @param ?string $errorMessage
     * @param ?string $errorCode
     * @param ?OrderInterface $order
     * @return void
     * @throws AlreadyExistsException
     * @throws ApiException
     * @throws InvalidClassException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function handleTransactionError(
        ?string $declineCode,
        ?string $errorMessage,
        ?string $errorCode = null,
        ?OrderInterface $order = null
    ): void {
        $signifydReason = ($this->gatewayErrorCodeMapper)($declineCode, $errorCode);

        if (isset($signifydReason) === false) {
            return;
        }

        // The gateway refused the payment of an order that had already been persisted, e.g. a
        // redirect or 3DS flow that failed on a retry. That is an order level transaction, not a
        // checkout one, so it goes through the same path used when the order gets canceled
        if ($order instanceof Order && empty($order->getId()) === false) {
            $this->handleOrderTransactionError($order, $signifydReason, $errorMessage);
            return;
        }

        if (isset($errorMessage)) {
            $this->transactionIntegration->setGatewayStatusMessage($errorMessage);
        }

        $this->transactionIntegration->setGatewayRefusedReason($signifydReason);
        $this->transactionIntegration->submitToTransactionApi();
    }

    /**
     * Records the gateway verdict on an already placed order and re-posts its transaction.
     *
     * @param Order $order
     * @param string $signifydReason
     * @param ?string $errorMessage
     * @return void
     */
    public function handleOrderTransactionError(Order $order, string $signifydReason, ?string $errorMessage): void
    {
        if ($this->paymentStatusHelper->hasPaymentRegistered($order)) {
            return;
        }

        $recorded = $this->paymentStatusHelper->recordGatewayStatus(
            $order,
            GatewayStatusCode::FAILURE,
            $signifydReason,
            $errorMessage
        );

        if ($recorded === false) {
            return;
        }

        ($this->processTransactionFactory->create())($order);
    }
}
