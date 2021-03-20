<?php

namespace Signifyd\Connect\Observer\Purchase;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Signifyd\Connect\Helper\PurchaseHelper;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Helper\ConfigHelper;
use Signifyd\Connect\Model\Casedata;
use Signifyd\Connect\Model\CasedataFactory;
use Signifyd\Connect\Model\ResourceModel\Casedata as CasedataResourceModel;
use Signifyd\Connect\Observer\Purchase;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\ResourceModel\Order as OrderResourceModel;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\State as AppState;

class AuthorizenetDirectpost extends Purchase
{
    /**
     * @var OrderFactory
     */
    protected $orderFactory;

    /**
     * AuthorizenetDirectpost constructor.
     * @param Logger $logger
     * @param PurchaseHelper $purchaseHelper
     * @param ConfigHelper $configHelper
     * @param CasedataFactory $casedataFactory
     * @param CasedataResourceModel $casedataResourceModel
     * @param OrderResourceModel $orderResourceModel
     * @param DateTime $dateTime
     * @param ScopeConfigInterface $scopeConfigInterface
     * @param StoreManagerInterface $storeManager
     * @param AppState $appState
     * @param OrderFactory $orderFactory
     */
    public function __construct(
        Logger $logger,
        PurchaseHelper $purchaseHelper,
        ConfigHelper $configHelper,
        CasedataFactory $casedataFactory,
        CasedataResourceModel $casedataResourceModel,
        OrderResourceModel $orderResourceModel,
        DateTime $dateTime,
        ScopeConfigInterface $scopeConfigInterface,
        StoreManagerInterface $storeManager,
        AppState $appState,
        OrderFactory $orderFactory
    )
    {
        parent::__construct(
            $logger,
            $purchaseHelper,
            $configHelper,
            $casedataFactory,
            $casedataResourceModel,
            $orderResourceModel,
            $dateTime,
            $scopeConfigInterface,
            $storeManager,
            $appState
        );

        $this->orderFactory = $orderFactory;
    }

    /**
     * @param Observer $observer
     * @param bool $checkOwnEventsMethods
     */
    public function execute(Observer $observer, $checkOwnEventsMethods = true)
    {
        /** @var \Magento\Framework\App\Request\Http $request */
        $request = $observer->getEvent()->getRequest();
        $orderIncrementId = $request->getParam('x_invoice_num');

        if (!empty($orderIncrementId)) {
            /** @var \Magento\Sales\Model\Order $order */
            $order = $this->orderFactory->create();
            $this->orderResourceModel->load($order, $orderIncrementId, 'increment_id');

            if ($order instanceof \Magento\Sales\Model\Order) {
                $observer->getEvent()->setOrder($order);
            }
        }

        parent::execute($observer, false);
    }
}
