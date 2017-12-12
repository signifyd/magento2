<?php
/**
 * Copyright 2017 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Signifyd\Connect\Observer;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Signifyd\Connect\Helper\PurchaseHelper;
use Signifyd\Connect\Helper\LogHelper;
use Signifyd\Connect\Helper\SignifydAPIMagento;

/**
 * Observer for purchase event. Sends order data to Signifyd service
 */
class Cancel implements ObserverInterface
{
    /**
     * @var \Signifyd\Connect\Helper\LogHelper
     */
    protected $logger;

    /**
     * @var \Signifyd\Connect\Helper\PurchaseHelper
     */
    protected $helper;

    /**
     * @var SignifydAPIMagento
     */
    protected $api;

    /**
     * @var ScopeConfigInterface
     */
    protected $coreConfig;

    public function __construct(
        LogHelper $logger,
        PurchaseHelper $helper,
        SignifydAPIMagento $api,
        ScopeConfigInterface $coreConfig
    ) {
        $this->logger = $logger;
        $this->helper = $helper;
        $this->api = $api;
        $this->coreConfig = $coreConfig;
    }

    public function execute(Observer $observer)
    {
        if(!$this->api->enabled()) return;

        try {
            /** @var $order Order */
            $order = $observer->getEvent()->getOrder();

            // Check if case already exists for this order
            if ($this->helper->doesCaseExist($order)) {
                $result = $this->helper->cancelCaseOnSignifyd($order);
            }
        } catch (\Exception $ex) {
            $this->logger->error($ex->getMessage());
        }
    }
}
