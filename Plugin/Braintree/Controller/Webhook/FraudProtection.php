<?php

namespace Signifyd\Connect\Plugin\Braintree\Controller\Webhook;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\TransactionRepositoryInterface;
use Magento\Sales\Model\Order;
use PayPal\Braintree\Controller\Webhook\FraudProtection as BraintreeFraudProtection;
use Signifyd\Connect\Api\CasedataRepositoryInterface;
use Signifyd\Connect\Helper\ConfigHelper;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\Api\Core\Client;
use Signifyd\Connect\Model\Payment\Braintree\ChargebackDataFactory;
use Signifyd\Connect\Model\Registry;

class FraudProtection
{
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
     * @var ChargebackDataFactory
     */
    public $chargebackDataFactory;

    /**
     * @var TransactionRepositoryInterface
     */
    public $transactionRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    public $searchCriteriaBuilder;

    /**
     * @var OrderRepositoryInterface
     */
    public $orderRepository;

    /**
     * @var CasedataRepositoryInterface
     */
    public $casedataRepository;

    /**
     * @var ConfigHelper
     */
    public $configHelper;

    /**
     * @var Client
     */
    public $client;

    /**
     * FraudProtection constructor.
     *
     * @param Logger $logger
     * @param Registry $registry
     * @param HttpRequest $request
     * @param ChargebackDataFactory $chargebackDataFactory
     * @param TransactionRepositoryInterface $transactionRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param OrderRepositoryInterface $orderRepository
     * @param CasedataRepositoryInterface $casedataRepository
     * @param ConfigHelper $configHelper
     * @param Client $client
     */
    public function __construct(
        Logger $logger,
        Registry $registry,
        HttpRequest $request,
        ChargebackDataFactory $chargebackDataFactory,
        TransactionRepositoryInterface $transactionRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        OrderRepositoryInterface $orderRepository,
        CasedataRepositoryInterface $casedataRepository,
        ConfigHelper $configHelper,
        Client $client
    ) {
        $this->logger = $logger;
        $this->registry = $registry;
        $this->request = $request;
        $this->chargebackDataFactory = $chargebackDataFactory;
        $this->transactionRepository = $transactionRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->orderRepository = $orderRepository;
        $this->casedataRepository = $casedataRepository;
        $this->configHelper = $configHelper;
        $this->client = $client;
    }

    /**
     * Plugin around execute method.
     *
     * The Braintree module only flags the order with the dispute status, it creates no credit memo
     * the chargeback could be reported on, so the chargeback of an opened dispute is reported here,
     * once the Braintree module is done with the notification. Any failure is only logged, so that
     * the webhook response and the Braintree processing stay untouched.
     *
     * @param BraintreeFraudProtection $subject
     * @param callable $proceed
     * @return mixed
     */
    public function aroundExecute(BraintreeFraudProtection $subject, callable $proceed)
    {
        $result = $proceed();

        // The Braintree module returns no result when its webhook is disabled or the request has no body
        if ($result === null) {
            return $result;
        }

        try {
            $this->reportChargeback();
        } catch (\Exception $e) {
            $this->logger->error('Failed to report Braintree chargeback: ' . $e->getMessage());
        } catch (\Error $e) {
            $this->logger->error('Failed to report Braintree chargeback: ' . $e->getMessage());
        }

        return $result;
    }

    /**
     * Report the chargeback of the dispute being notified, if any, for each order it belongs to.
     *
     * @return void
     */
    public function reportChargeback()
    {
        $notification = $this->getNotification();

        if ($notification === null) {
            return;
        }

        $chargebackDataModel = $this->chargebackDataFactory->create();
        $chargebackData = $chargebackDataModel($notification);

        if (empty($chargebackData)) {
            return;
        }

        $transactionId = $chargebackDataModel->getTransactionId($chargebackDataModel->getDispute($notification));

        if (empty($transactionId)) {
            $this->logger->error(
                'Chargeback skipped: Braintree dispute ' . $chargebackData['chargebackId'] . ' has no transaction'
            );
            return;
        }

        foreach ($this->getOrders($transactionId) as $order) {
            $this->createChargeback($order, $chargebackData);
        }
    }

    /**
     * Parse the Braintree webhook notification out of the request.
     *
     * The Braintree credentials needed to verify the signature are already set by the Braintree
     * adapter the webhook controller depends on.
     *
     * @return \Braintree\WebhookNotification|null
     */
    public function getNotification()
    {
        $signature = $this->request->getPost('bt_signature');
        $payload = $this->request->getPost('bt_payload');

        if (empty($signature) || empty($payload)) {
            return null;
        }

        return \Braintree\WebhookNotification::parse($signature, $payload);
    }

    /**
     * Get the orders of the disputed transaction, found the same way the Braintree module does.
     *
     * @param string $transactionId
     * @return Order[]
     */
    public function getOrders($transactionId)
    {
        $this->searchCriteriaBuilder->addFilter('txn_id', $transactionId);
        $transactions = $this->transactionRepository->getList($this->searchCriteriaBuilder->create());
        $orders = [];

        foreach ($transactions->getItems() as $transaction) {
            $orderId = (int) $transaction->getOrderId();

            // Authorization and capture may share the same transaction id
            if ($orderId > 0 && isset($orders[$orderId]) === false) {
                $orders[$orderId] = $this->orderRepository->get($orderId);
            }
        }

        return $orders;
    }

    /**
     * Create the chargeback of the order on Signifyd.
     *
     * Follows the same rules applied to the chargebacks reported in place of a Record Return, only
     * reporting it for orders with a Signifyd guarantee.
     *
     * @param Order $order
     * @param array $chargebackData
     * @return bool
     */
    public function createChargeback(Order $order, array $chargebackData)
    {
        if ($this->configHelper->isEnabled($order) == false) {
            return false;
        }

        $case = $this->casedataRepository->getByOrderId($order->getId());

        if ($case->isEmpty() || empty($case->getCode())) {
            $this->logger->debug(
                'Chargeback skipped: case not found for order ' . $order->getIncrementId(),
                ['entity' => $order]
            );
            return false;
        }

        $guarantee = $case->getData('guarantee');

        if (empty($guarantee) || in_array($guarantee, ['DECLINED', 'REJECT', 'N/A'])) {
            $this->logger->debug("Chargeback skipped: current guarantee is {$guarantee}", ['entity' => $order]);
            return false;
        }

        $this->registry->setData(Registry::CHARGEBACK_DATA_REGISTRY_KEY, $chargebackData);

        try {
            return $this->client->createChargeback($order, $case);
        } finally {
            $this->registry->setData(Registry::CHARGEBACK_DATA_REGISTRY_KEY);
        }
    }
}
