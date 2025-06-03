<?php

namespace Signifyd\Connect\Model\Api;

use Braintree\Exception;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magento\Framework\Stdlib\DateTime\DateTimeFactory;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResourceModel;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\Payment\Transaction\CollectionFactory as TransactionCollectionFactory;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\CasedataFactory;
use Signifyd\Connect\Model\PaymentVerificationFactory;
use Signifyd\Connect\Model\ResourceModel\Casedata as CasedataResourceModel;
use Signifyd\Connect\Model\ScaPreAuth\ScaEvaluation;

class Transactions
{
    /**
     * @var TransactionCollectionFactory
     */
    public $transactionCollectionFactory;

    /**
     * @var DateTimeFactory
     */
    public $dateTimeFactory;

    /**
     * @var JsonSerializer
     */
    public $jsonSerializer;

    /**
     * @var PaymentVerificationFactory
     */
    public $paymentVerificationFactory;

    /**
     * @var Logger
     */
    public $logger;

    /**
     * @var QuoteResourceModel
     */
    public $quoteResourceModel;

    /**
     * @var PaymentMethodFactory
     */
    public $paymentMethodFactory;

    /**
     * @var QuoteFactory
     */
    public $quoteFactory;

    /**
     * @var CasedataFactory
     */
    public $casedataFactory;

    /**
     * @var CasedataResourceModel
     */
    public $casedataResourceModel;

    /**
     * @var ScaEvaluation
     */
    public $scaEvaluation;

    /**
     * @var VerificationsFactory
     */
    public $verificationsFactory;

    /**
     * @var CheckoutPaymentDetailsFactory
     */
    public $checkoutPaymentDetailsFactory;

    /**
     * @var ParentTransactionIdFactory
     */
    public $parentTransactionIdFactory;

    /**
     * @var GatewayStatusMessageFactory
     */
    public $gatewayStatusMessageFactory;

    /**
     * @var GatewayErrorCodeFactory
     */
    public $gatewayErrorCodeFactory;

    /**
     * @var PaypalPendingReasonCodeFactory
     */
    public $paypalPendingReasonCodeFactory;

    /**
     * @var PaypalProtectionEligibilityFactory
     */
    public $paypalProtectionEligibilityFactory;

    /**
     * @var PaypalProtectionEligibilityTypeFactory
     */
    public $paypalProtectionEligibilityTypeFactory;

    /**
     * @var SourceAccountDetailsFactory
     */
    public $sourceAccountDetailsFactory;

    /**
     * @var AcquirerDetailsFactory
     */
    public $acquirerDetailsFactory;

    /**
     * Transactions construct.
     *
     * @param TransactionCollectionFactory $transactionCollectionFactory
     * @param DateTimeFactory $dateTimeFactory
     * @param JsonSerializer $jsonSerializer
     * @param PaymentVerificationFactory $paymentVerificationFactory
     * @param Logger $logger
     * @param QuoteResourceModel $quoteResourceModel
     * @param PaymentMethodFactory $paymentMethodFactory
     * @param QuoteFactory $quoteFactory
     * @param CasedataFactory $casedataFactory
     * @param CasedataResourceModel $casedataResourceModel
     * @param ScaEvaluation $scaEvaluation
     * @param VerificationsFactory $verificationsFactory
     * @param CheckoutPaymentDetailsFactory $checkoutPaymentDetailsFactory
     * @param ParentTransactionIdFactory $parentTransactionIdFactory
     * @param GatewayStatusMessageFactory $gatewayStatusMessageFactory
     * @param GatewayErrorCodeFactory $gatewayErrorCodeFactory
     * @param PaypalPendingReasonCodeFactory $paypalPendingReasonCodeFactory
     * @param PaypalProtectionEligibilityFactory $paypalProtectionEligibilityFactory
     * @param PaypalProtectionEligibilityTypeFactory $paypalProtectionEligibilityTypeFactory
     * @param SourceAccountDetailsFactory $sourceAccountDetailsFactory
     * @param AcquirerDetailsFactory $acquirerDetailsFactory
     */
    public function __construct(
        TransactionCollectionFactory $transactionCollectionFactory,
        DateTimeFactory $dateTimeFactory,
        JsonSerializer $jsonSerializer,
        PaymentVerificationFactory $paymentVerificationFactory,
        Logger $logger,
        QuoteResourceModel $quoteResourceModel,
        PaymentMethodFactory $paymentMethodFactory,
        QuoteFactory $quoteFactory,
        CasedataFactory $casedataFactory,
        CasedataResourceModel $casedataResourceModel,
        ScaEvaluation $scaEvaluation,
        VerificationsFactory $verificationsFactory,
        CheckoutPaymentDetailsFactory $checkoutPaymentDetailsFactory,
        ParentTransactionIdFactory $parentTransactionIdFactory,
        GatewayStatusMessageFactory $gatewayStatusMessageFactory,
        GatewayErrorCodeFactory $gatewayErrorCodeFactory,
        PaypalPendingReasonCodeFactory $paypalPendingReasonCodeFactory,
        PaypalProtectionEligibilityFactory $paypalProtectionEligibilityFactory,
        PaypalProtectionEligibilityTypeFactory $paypalProtectionEligibilityTypeFactory,
        SourceAccountDetailsFactory $sourceAccountDetailsFactory,
        AcquirerDetailsFactory $acquirerDetailsFactory
    ) {
        $this->transactionCollectionFactory = $transactionCollectionFactory;
        $this->dateTimeFactory = $dateTimeFactory;
        $this->jsonSerializer = $jsonSerializer;
        $this->paymentVerificationFactory = $paymentVerificationFactory;
        $this->logger = $logger;
        $this->quoteResourceModel = $quoteResourceModel;
        $this->paymentMethodFactory = $paymentMethodFactory;
        $this->quoteFactory = $quoteFactory;
        $this->casedataFactory = $casedataFactory;
        $this->casedataResourceModel = $casedataResourceModel;
        $this->scaEvaluation = $scaEvaluation;
        $this->verificationsFactory = $verificationsFactory;
        $this->checkoutPaymentDetailsFactory = $checkoutPaymentDetailsFactory;
        $this->parentTransactionIdFactory = $parentTransactionIdFactory;
        $this->gatewayStatusMessageFactory = $gatewayStatusMessageFactory;
        $this->gatewayErrorCodeFactory = $gatewayErrorCodeFactory;
        $this->paypalPendingReasonCodeFactory = $paypalPendingReasonCodeFactory;
        $this->paypalProtectionEligibilityFactory = $paypalProtectionEligibilityFactory;
        $this->paypalProtectionEligibilityTypeFactory = $paypalProtectionEligibilityTypeFactory;
        $this->sourceAccountDetailsFactory = $sourceAccountDetailsFactory;
        $this->acquirerDetailsFactory = $acquirerDetailsFactory;
    }

    /**
     * Construct a new Transactions object
     *
     * @param Order|Quote $entity
     * @param mixed $checkoutToken
     * @param array $methodData
     * @return array
     */
    public function __invoke($entity, $checkoutToken = null, $methodData = [])
    {
        if ($entity instanceof Order) {
            $transactions = $this->makeTransactions($entity);
        } elseif ($entity instanceof Quote) {
            $transactions = $this->makeCheckoutTransactions($entity, $checkoutToken, $methodData);
        } else {
            $transactions = [];
        }

        return $transactions;
    }

    /**
     * Construct a new Transactions object
     *
     * @param Order $order
     * @return array
     */
    protected function makeTransactions(Order $order)
    {
        $lastTransaction = $order->getPayment()->getLastTransId();
        $transactionsFromOrder = $this->transactionCollectionFactory->create()
            ->addFieldToFilter('txn_id', ['eq' => $lastTransaction]);
        $transactionFromOrder = $transactionsFromOrder->getFirstItem();

        $transactions = [];
        $lastTransaction = [];

        if ($transactionFromOrder->isEmpty()) {
            $dateTime = $this->dateTimeFactory->create();
            $transactionDate = $dateTime->gmtDate();
        } else {
            $transactionDate = $transactionFromOrder->getData('created_at');
        }

        $transactionId = $this->getTransactionId($order);
        $makePaymentMethod = $this->paymentMethodFactory->create();
        $verifications = $this->verificationsFactory->create();
        $checkoutPaymentDetails = $this->checkoutPaymentDetailsFactory->create();
        $parentTransactionId = $this->parentTransactionIdFactory->create();
        $gatewayStatusMessage = $this->gatewayStatusMessageFactory->create();
        $gatewayErrorCode = $this->gatewayErrorCodeFactory->create();
        $paypalPendingReasonCode = $this->paypalPendingReasonCodeFactory->create();
        $paypalProtectionEligibility = $this->paypalProtectionEligibilityFactory->create();
        $paypalProtectionEligibilityType = $this->paypalProtectionEligibilityTypeFactory->create();
        $sourceAccountDetails = $this->sourceAccountDetailsFactory->create();
        $acquirerDetails = $this->acquirerDetailsFactory->create();

        $lastTransaction['gatewayStatusCode'] = 'SUCCESS';
        $lastTransaction['paymentMethod'] = $makePaymentMethod($order);
        $lastTransaction['checkoutPaymentDetails'] = $checkoutPaymentDetails($order);
        $lastTransaction['amount'] = $order->getGrandTotal();
        $lastTransaction['currency'] = $order->getOrderCurrencyCode();
        $lastTransaction['gateway'] = $order->getPayment()->getMethod();
        $lastTransaction['sourceAccountDetails'] = $sourceAccountDetails();
        $lastTransaction['acquirerDetails'] = $acquirerDetails();
        $lastTransaction['gatewayErrorCode'] = $gatewayErrorCode();
        $lastTransaction['gatewayStatusMessage'] = $gatewayStatusMessage();
        $lastTransaction['createdAt'] = date('c', strtotime($transactionDate));
        $lastTransaction['parentTransactionId'] = $parentTransactionId();
        $lastTransaction['scaExemptionRequested'] = $this->makeScaExemptionRequested($order->getQuoteId());
        $lastTransaction['verifications'] = $verifications($order);
        $lastTransaction['threeDsResult'] = $this->makeThreeDsResult($order->getQuoteId());
        $lastTransaction['paypalPendingReasonCode'] = $paypalPendingReasonCode();
        $lastTransaction['paypalProtectionEligibility'] = $paypalProtectionEligibility();
        $lastTransaction['paypalProtectionEligibilityType'] = $paypalProtectionEligibilityType();

        if (isset($transactionId) === false) {
            $transactionId = sha1($this->jsonSerializer->serialize($lastTransaction));
        }

        $lastTransaction['transactionId'] = $transactionId;
        $transactions[] = $lastTransaction;

        return $transactions;
    }

    /**
     * Make checkout transactions method.
     *
     * @param Quote $quote
     * @param mixed $checkoutToken
     * @param array $methodData
     * @return array
     */
    protected function makeCheckoutTransactions(Quote $quote, $checkoutToken, $methodData = [])
    {
        $reservedOrderId = $quote->getReservedOrderId();

        if (empty($reservedOrderId)) {
            $quote->reserveOrderId();
            $reservedOrderId = $quote->getReservedOrderId();
            $this->quoteResourceModel->save($quote);
        }

        $gatewayStatusMessage = $this->gatewayStatusMessageFactory->create();
        $checkoutTransaction = [];
        $checkoutTransaction['checkoutId'] = $checkoutToken;
        $checkoutTransaction['orderId'] = $reservedOrderId;
        $errorCode = $methodData['gatewayRefusedReason'] ?? "CARD_DECLINED";
        $statusMessage = $methodData['gatewayStatusMessage'] ?? $gatewayStatusMessage();
        $gateway = $methodData['gateway'] ?? null;
        $makePaymentMethod = $this->paymentMethodFactory->create();
        $checkoutPaymentDetails = $this->checkoutPaymentDetailsFactory->create();
        $paypalPendingReasonCode = $this->paypalPendingReasonCodeFactory->create();
        $paypalProtectionEligibility = $this->paypalProtectionEligibilityFactory->create();
        $paypalProtectionEligibilityType = $this->paypalProtectionEligibilityTypeFactory->create();
        $sourceAccountDetails = $this->sourceAccountDetailsFactory->create();
        $acquirerDetails = $this->acquirerDetailsFactory->create();

        $transactions = [];
        $transaction = [];
        $transaction['gatewayStatusCode'] = 'FAILURE';
        $transaction['paymentMethod'] = $makePaymentMethod($quote);
        $transaction['checkoutPaymentDetails'] = $checkoutPaymentDetails($quote, $methodData);
        $transaction['amount'] = $quote->getGrandTotal();
        $transaction['currency'] = $quote->getBaseCurrencyCode();
        $transaction['gateway'] = $gateway;
        $transaction['sourceAccountDetails'] = $sourceAccountDetails();
        $transaction['acquirerDetails'] = $acquirerDetails();
        $transaction['gatewayErrorCode'] = $errorCode;
        $transaction['gatewayStatusMessage'] = $statusMessage;
        $transaction['scaExemptionRequested'] = $this->makeScaExemptionRequested($quote->getId());
        $transaction['threeDsResult'] = $this->makeThreeDsResult($quote->getId());
        $transaction['paypalPendingReasonCode'] = $paypalPendingReasonCode();
        $transaction['paypalProtectionEligibility'] = $paypalProtectionEligibility();
        $transaction['paypalProtectionEligibilityType'] = $paypalProtectionEligibilityType();
        $transaction['transactionId'] = sha1($this->jsonSerializer->serialize($transaction));

        $transactions[] = $transaction;
        $checkoutTransaction['transactions'] = $transactions;

        return $checkoutTransaction;
    }

    /**
     * Gets transaction ID for order payment method.
     *
     * @param Order $order
     * @return int|null
     */
    protected function getTransactionId(Order $order)
    {
        try {
            $paymentMethod = $order->getPayment()->getMethod();
            $transactionIdAdapter = $this->paymentVerificationFactory->createPaymentTransactionId($paymentMethod);

            $message = 'Getting transaction ID using ' . get_class($transactionIdAdapter);
            $this->logger->debug($message, ['entity' => $order]);

            $transactionId = $transactionIdAdapter->getData($order);

            if (empty($transactionId)) {
                return null;
            }

            return $transactionId;
        } catch (Exception $e) {
            $this->logger->error('Error fetching transaction ID: ' . $e->getMessage(), ['entity' => $order]);
            return null;
        }
    }

    /**
     * Make sca exemption requested method.
     *
     * @param mixed $quoteId
     * @return string|null
     */
    protected function makeScaExemptionRequested($quoteId = null)
    {
        if (isset($quoteId)) {
            $quote = $this->quoteFactory->create();
            $this->quoteResourceModel->load($quote, $quoteId);

            if ($quote->isEmpty()) {
                return null;
            }

            /** @var \Signifyd\Connect\Model\Casedata $case */
            $case = $this->casedataFactory->create();
            $this->casedataResourceModel->load($case, $quoteId, 'quote_id');

            if ($case->isEmpty()) {
                return null;
            }

            /** @var \Signifyd\Models\ScaEvaluation $scaEvaluation */
            $scaEvaluation = $this->scaEvaluation->getScaEvaluation($quote);

            if ($scaEvaluation !== false &&
                isset($scaEvaluation->exemptionDetails) &&
                isset($scaEvaluation->exemptionDetails->exemption)
            ) {
                return $scaEvaluation->exemptionDetails->exemption;
            }
        }

        return null;
    }

    /**
     * MakeThreeDsResult method should be extended/intercepted by plugin to add value to it.
     *
     * These are details about the result of the 3D Secure authentication
     * This method should be extended/intercepted by plugin to add value to it
     *
     * @param mixed $quoteId
     * @return array|bool|float|int|mixed|string|null
     */
    public function makeThreeDsResult($quoteId)
    {
        $case = $this->casedataFactory->create();
        $this->casedataResourceModel->load($case, $quoteId, 'quote_id');

        if (empty($case->getEntries('threeDs'))) {
            return null;
        }

        try {
            return $this->jsonSerializer->unserialize($case->getEntries('threeDs'));
        } catch (\InvalidArgumentException $e) {
            return null;
        }
    }
}
