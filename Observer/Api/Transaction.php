<?php

namespace Signifyd\Connect\Observer\Api;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Signifyd\Connect\Helper\ConfigHelper;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Helper\PurchaseHelper;
use Signifyd\Connect\Model\Casedata;
use Signifyd\Connect\Model\CasedataFactory;
use Signifyd\Connect\Model\ResourceModel\Casedata as CasedataResourceModel;

class Transaction implements ObserverInterface
{
    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var PurchaseHelper
     */
    protected $purchaseHelper;

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
     * Transaction constructor.
     * @param Logger $logger
     * @param PurchaseHelper $purchaseHelper
     * @param CasedataFactory $casedataFactory
     * @param CasedataResourceModel $casedataResourceModel
     * @param ConfigHelper $configHelper
     */
    public function __construct(
        Logger $logger,
        PurchaseHelper $purchaseHelper,
        CasedataFactory $casedataFactory,
        CasedataResourceModel $casedataResourceModel,
        ConfigHelper $configHelper
    ) {
        $this->logger = $logger;
        $this->purchaseHelper = $purchaseHelper;
        $this->casedataFactory = $casedataFactory;
        $this->casedataResourceModel = $casedataResourceModel;
        $this->configHelper = $configHelper;
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
                    $saleTransaction = [];
                    $saleTransaction['checkoutId'] = $case->getCheckoutToken();
                    $saleTransaction['orderId'] = $order->getIncrementId();
                    $saleTransaction['transactions'] = $this->purchaseHelper->makeTransactions($order);
                    $this->purchaseHelper->postTransactionToSignifyd($saleTransaction, $order);
                }
            } catch (\Exception $e) {
                $this->logger->debug($e->getMessage());
            }
        }
    }
}
