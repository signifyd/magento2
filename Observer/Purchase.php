<?php
/**
 * Copyright 2015 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Signifyd\Connect\Observer;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address;
use Psr\Log\LoggerInterface;
use \Magento\Framework\ObjectManagerInterface;
use Signifyd\Connect\Helper\PurchaseHelper;
use Signifyd\Connect\Helper\LogHelper;
use Signifyd\Connect\Helper\SignifydAPIMagento;
use Signifyd\Connect\Model\CaseRetry;

/**
 * Observer for purchase event. Sends order data to Signifyd service
 */
class Purchase implements ObserverInterface
{
    /**
     * @var \Signifyd\Connect\Helper\LogHelper
     */
    protected $_logger;

    /**
     * @var \Signifyd\Connect\Helper\PurchaseHelper
     */
    protected $_helper;

    /**
     * @var SignifydAPIMagento
     */
    protected $_api;

    public function __construct(
        LogHelper $logger,
        PurchaseHelper $helper,
        SignifydAPIMagento $api
    ) {
        $this->_logger = $logger;
        $this->_helper = $helper;
        $this->_api = $api;
    }

    public function execute(Observer $observer)
    {
        if(!$this->_api->enabled()) return;

        try {
            /** @var $order Order */
            $order = $observer->getEvent()->getOrder();

            // Check if a payment is available for this order yet
            if($order->getState() == \Magento\Sales\Model\Order::STATE_PENDING_PAYMENT) {
                return;
            }

            // Check if case already exists for this order
            if ($this->_helper->doesCaseExist($order)) {
                return;
            }

            $orderData = $this->_helper->processOrderData($order);

            // Add order to database
            $case = $this->_helper->createNewCase($order);

            // Post case to signifyd service
            $result = $this->_helper->postCaseToSignifyd($orderData, $order);
            if($result){
                $case->setCode($result);
                $case->setMagentoStatus(CaseRetry::IN_REVIEW_STATUS)->setUpdated(strftime('%Y-%m-%d %H:%M:%S', time()));
                $case->save();
            }
        } catch (\Exception $ex) {
            $this->_logger->error($ex->getMessage());
        }
    }
}
