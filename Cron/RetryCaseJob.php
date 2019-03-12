<?php

/**
 * Copyright 2016 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Signifyd\Connect\Cron;

use Magento\Framework\ObjectManagerInterface;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Helper\PurchaseHelper;
use Signifyd\Connect\Helper\Retry;
use Signifyd\Connect\Model\CaseRetry;

class RetryCaseJob
{
    /**
     * @var Logger
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

    /**
     * @var \Signifyd\Connect\Helper\Retry
     */
    protected $caseRetryObj;

    public function __construct(
        ObjectManagerInterface $objectManager,
        PurchaseHelper $helper,
        Logger $logger,
        Retry $caseRetryObj
    ) {
        $this->_objectManager = $objectManager;
        $this->_helper = $helper;
        $this->_logger = $logger;
        $this->caseRetryObj = $caseRetryObj;
    }

    /**
     * Entry point to Cron job
     * @return $this
     */
    public function execute() {
        $this->_logger->request("Starting retry job");
        $this->retry();
        return $this;
    }

    /**
     * Main Retry Method to start retry cycle
     */
    public function retry()
    {
        $this->_logger->request("Main retry method called");

        /**
         * Getting all the cases that were not submitted to Signifyd
         */
        $waitingCases = $this->caseRetryObj->getRetryCasesByStatus(CaseRetry::WAITING_SUBMISSION_STATUS);

        foreach ($waitingCases as $case) {
            $this->_logger->request("Signifyd: preparing for send case no: {$case['order_increment']}");

            $order = $this->getOrder($case['order_increment']);

            $caseData = $this->_helper->processOrderData($order);
            $result = $this->_helper->postCaseToSignifyd($caseData, $order);

            if ($result) {
                /** @var \Signifyd\Connect\Model\Casedata $caseObj */
                $caseObj = $this->_objectManager->create('Signifyd\Connect\Model\Casedata')
                    ->load($case->getOrderIncrement())
                    ->setCode($result)
                    ->setMagentoStatus(CaseRetry::IN_REVIEW_STATUS)
                    ->setUpdated(strftime('%Y-%m-%d %H:%M:%S', time()));
                $caseObj->save();
            }
        }

        /**
         * Getting all the cases that are awaiting review from Signifyd
         */
        $inReviewCases = $this->caseRetryObj->getRetryCasesByStatus(CaseRetry::IN_REVIEW_STATUS);

        foreach ($inReviewCases as $case) {
            $this->_logger->request("Signifyd: preparing for review case no: {$case['order_increment']}");

            $this->caseRetryObj->processInReviewCase($case, $this->getOrder($case['order_increment']));
        }

        /**
         * Getting all the cases that need processing after the response was received
         */
        $inProcessingCases = $this->caseRetryObj->getRetryCasesByStatus(CaseRetry::PROCESSING_RESPONSE_STATUS);

        foreach ($inProcessingCases as $case) {
            $this->_logger->request("Signifyd: preparing for review case no: {$case['order_increment']}");

            $this->caseRetryObj->processResponseStatus($case, $this->getOrder($case['order_increment']));
        }

        $this->_logger->request("Main retry method ended");

        return;
    }

    public function getOrder($incrementId)
    {
        return $this->_objectManager->get('Magento\Sales\Model\Order')->loadByIncrementId($incrementId);
    }
}
