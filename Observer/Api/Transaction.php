<?php

namespace Signifyd\Connect\Observer\Api;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Signifyd\Connect\Helper\ConfigHelper;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\Api\Core\Client;
use Signifyd\Connect\Model\Casedata;
use Signifyd\Connect\Model\CasedataFactory;
use Signifyd\Connect\Model\ResourceModel\Casedata as CasedataResourceModel;
use Signifyd\Connect\Model\Api\TransactionsFactory;

class Transaction implements ObserverInterface
{
    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var CasedataFactory
     */
    protected $casedataFactory;

    /**
     * @var CasedataResourceModel
     */
    protected $casedataResourceModel;

    /**
     * @var ConfigHelper
     */
    protected $configHelper;

    /**
     * @var TransactionsFactory
     */
    protected $transactionsFactory;

    /**
     * @var Client
     */
    protected $client;

    /**
     * @var JsonSerializer
     */
    protected $jsonSerializer;

    /**
     * Transaction constructor.
     * @param Logger $logger
     * @param CasedataFactory $casedataFactory
     * @param CasedataResourceModel $casedataResourceModel
     * @param ConfigHelper $configHelper
     * @param TransactionsFactory $transactionsFactory
     * @param Client $client
     * @param JsonSerializer $jsonSerializer
     */
    public function __construct(
        Logger $logger,
        CasedataFactory $casedataFactory,
        CasedataResourceModel $casedataResourceModel,
        ConfigHelper $configHelper,
        TransactionsFactory $transactionsFactory,
        Client $client,
        JsonSerializer $jsonSerializer
    ) {
        $this->logger = $logger;
        $this->casedataFactory = $casedataFactory;
        $this->casedataResourceModel = $casedataResourceModel;
        $this->configHelper = $configHelper;
        $this->transactionsFactory = $transactionsFactory;
        $this->client = $client;
        $this->jsonSerializer = $jsonSerializer;
    }

    public function execute(Observer $observer)
    {
        if ($this->configHelper->isEnabled()) {
            try {
                /** @var $order \Magento\Sales\Model\Order */
                $order = $observer->getEvent()->getOrder();

                $paymentMethod = $order->getPayment()->getMethod();

                if ($this->configHelper->isPaymentRestricted($paymentMethod)) {
                    $message = 'Case creation for order ' . $order->getIncrementId() . ' with payment ' .
                        $paymentMethod . ' is restricted';
                    $this->logger->debug($message, ['entity' => $order]);
                    return;
                }

                /** @var $case \Signifyd\Connect\Model\Casedata */
                $case = $this->casedataFactory->create();
                $this->casedataResourceModel->load($case, $order->getId(), 'order_id');

                if ($case->isEmpty() == false && $case->getPolicyName() == Casedata::PRE_AUTH) {
                    $this->logger->info("Sending pre_auth transaction to Signifyd for order
                        {$case->getOrderIncrement()}");
                    $makeTransactions = $this->transactionsFactory->create();
                    $saleTransaction = [];
                    $saleTransaction['checkoutId'] = $case->getCheckoutToken();
                    $saleTransaction['orderId'] = $order->getIncrementId();
                    $saleTransaction['transactions'] = $makeTransactions($order);
                    $this->client->postTransactionToSignifyd($saleTransaction, $order);
                }
            } catch (\Exception $e) {
                $this->logger->debug($e->getMessage());
            }
        }
    }
}
