<?php
/**
 * Copyright 2017 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Signifyd\Connect\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Signifyd\Connect\Helper\PurchaseHelper;
use Signifyd\Connect\Helper\LogHelper;

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
     * @var \Signifyd\Connect\Helper\ConfigHelper
     */
    protected $configHelper;

    public function __construct(
        LogHelper $logger,
        PurchaseHelper $helper,
        \Signifyd\Connect\Helper\ConfigHelper $configHelper
    ) {
        $this->logger = $logger;
        $this->helper = $helper;
        $this->configHelper = $configHelper;
    }

    public function execute(Observer $observer)
    {
        try {
            /** @var $order Order */
            $order = $observer->getEvent()->getOrder();

            if ($this->configHelper->isEnabled($order) == false) {
                return;
            }

            // Check if case already exists for this order
            if ($this->helper->doesCaseExist($order)) {
                $this->helper->cancelCaseOnSignifyd($order);
            }
        } catch (\Exception $ex) {
            $this->logger->error($ex->getMessage());
        }
    }
}
