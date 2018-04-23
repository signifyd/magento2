<?php

namespace Signifyd\Connect\Observer\Purchase;

use Signifyd\Connect\Observer\Purchase;

use Signifyd\Connect\Helper\LogHelper;
use Signifyd\Connect\Helper\PurchaseHelper;
use Signifyd\Connect\Helper\SignifydAPIMagento;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Sales\Model\Order;

class AuthorizenetDirectpost extends Purchase
{
    /**
     * @var Order
     */
    protected $order;

    /**
     * OwnEvent constructor.
     * @param LogHelper $logger
     * @param PurchaseHelper $helper
     * @param SignifydAPIMagento $api
     * @param ScopeConfigInterface $coreConfig
     * @param Order $order
     */
    public function __construct(
        LogHelper $logger,
        PurchaseHelper $helper,
        SignifydAPIMagento $api,
        ScopeConfigInterface $coreConfig,
        Order $order
    ) {
        $this->order = $order;

        parent::__construct($logger, $helper, $api, $coreConfig);
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        /** @var \Magento\Framework\App\Request\Http $request */
        $request = $observer->getEvent()->getRequest();
        $orderIncrementId = $request->getParam('x_invoice_num');
        $this->order->loadByIncrementId($orderIncrementId);
        $observer->getEvent()->setOrder($this->order);

        return parent::execute($observer, false);
    }
}