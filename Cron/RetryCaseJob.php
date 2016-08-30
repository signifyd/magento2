<?php

/**
 * Copyright � 2016 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Signifyd\Connect\Cron;

use Magento\Framework\ObjectManagerInterface;
use Signifyd\Connect\Helper\LogHelper;
use Signifyd\Connect\Helper\PurchaseHelper;

class RetryCaseJob
{
    /**
     * @var \Signifyd\Connect\Helper\LogHelper
     */
    protected $_logger;

    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $_objectManager;

    /**
     * @var \Signifyd\Connect\Helper\PurchaseHelper
     */
    protected $_helper;

    public function __construct(
        ObjectManagerInterface $objectManager,
        PurchaseHelper $helper,
        LogHelper $logger
    ) {
        $this->_objectManager = $objectManager;
        $this->_helper = $helper;
        $this->_logger = $logger;
    }

    public function execute() {
        $this->_logger->request("Starting retry job");
        $this->processRetryQueue();
        return $this;
    }

    /**
     * Run through up to $max items in the retry queue
     * @param int $max The maximum numbers of items to process
     */
    public function processRetryQueue($max = 99999)
    {
        /** @var $retryEntity \Signifyd\Connect\Model\CaseRetry */
        $retryEntity = $this->_objectManager->get('Signifyd\Connect\Model\CaseRetry');
        $failed_orders = $retryEntity->getCollection();
        $process_count = 0;
        try {
            foreach ($failed_orders as $failed_order) {
                $this->_logger->request("Order up");
                /** @var $failed_order \Signifyd\Connect\Model\CaseRetry */
                if ($process_count++ >= $max) {
                    return;
                }
                $order = $this->_objectManager->get('Magento\Sales\Model\Order')->loadByIncrementId($failed_order->getOrderIncrement());

                $this->_logger->request("Load");
                $orderData = $this->_helper->getCase($order);
                if (!$this->_helper->doesCaseExist($order)) {
                    $this->_logger->request("No case");
                    $orderData = $this->_helper->processOrderData($order);
                    $this->_helper->createNewCase($order);
                }

                $this->_logger->request("Start retry");
                if ($this->_helper->retryCase($orderData, $order)) {
                    $this->_logger->request("Completed retry " . $failed_order->getOrderIncrement());
                    $failed_order->delete();
                } else {
                    $this->_logger->error("Failed retry " . $failed_order->getOrderIncrement());
                }
            }
        } catch (\Exception $e) {
            $this->_logger->error($e->__toString());
        }
    }
}
