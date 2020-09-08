<?php

/**
 * Copyright 2016 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Signifyd\Connect\Cron;

use Magento\Framework\ObjectManagerInterface;
use Magento\Sales\Model\ResourceModel\Order as OrderResourceModel;
use Magento\Sales\Model\OrderFactory;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Helper\PurchaseHelper;
use Signifyd\Connect\Helper\Retry;
use Signifyd\Connect\Model\Casedata;
use Signifyd\Connect\Model\ResourceModel\Casedata as CasedataResourceModel;
use Signifyd\Connect\Model\CasedataFactory;

class RetryCaseJob
{
    /**
     * @var Logger
     */
    protected $logger;

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

    /**
     * @var OrderResourceModel
     */
    protected $orderResourceModel;

    /**
     * @var OrderFactory
     */
    protected $orderFactory;

    /**
     * @var CasedataResourceModel
     */
    protected $casedataResourceModel;

    /**
     * @var CasedataFactory\
     */
    protected $casedataFactory;

    /**
     * @var \StripeIntegration\Payments\Model\Config
     */
    protected $stripeConfig;

    /**
     * RetryCaseJob constructor.
     * @param ObjectManagerInterface $objectManager
     * @param PurchaseHelper $helper
     * @param Logger $logger
     * @param Retry $caseRetryObj
     * @param OrderResourceModel $orderResourceModel
     * @param OrderFactory $orderFactory
     * @param CasedataResourceModel $casedataResourceModel
     * @param CasedataFactory $casedataFactory
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        PurchaseHelper $helper,
        Logger $logger,
        Retry $caseRetryObj,
        OrderResourceModel $orderResourceModel,
        OrderFactory $orderFactory,
        CasedataResourceModel $casedataResourceModel,
        CasedataFactory $casedataFactory
    ) {
        $this->_objectManager = $objectManager;
        $this->_helper = $helper;
        $this->logger = $logger;
        $this->caseRetryObj = $caseRetryObj;
        $this->orderResourceModel = $orderResourceModel;
        $this->orderFactory = $orderFactory;
        $this->casedataResourceModel = $casedataResourceModel;
        $this->casedataFactory = $casedataFactory;
    }

    /**
     * Entry point to Cron job
     * @return $this
     */
    public function execute()
    {
        $this->logger->debug("Main retry method called");

        /**
         * Getting all the cases that were not submitted to Signifyd
         */
        $waitingCases = $this->caseRetryObj->getRetryCasesByStatus(Casedata::WAITING_SUBMISSION_STATUS);

        foreach ($waitingCases as $case) {
            $message = "Signifyd: preparing for send case no: {$case['order_increment']}";
            $this->logger->debug($message, ['entity' => $case]);

            $order = $this->getOrder($case['order_increment']);

            $caseData = $this->_helper->processOrderData($order);
            $caseResponse = $this->_helper->postCaseToSignifyd($caseData, $order);

            if (is_object($caseResponse)) {
                /** @var Casedata $caseObj */
                $caseObj = $this->casedataFactory->create();
                $this->casedataResourceModel->load($caseObj, $case->getOrderIncrement());

                $caseObj->setCode($caseResponse->getCaseId())
                    ->setMagentoStatus(Casedata::IN_REVIEW_STATUS)
                    ->setUpdated(strftime('%Y-%m-%d %H:%M:%S', time()));

                $this->casedataResourceModel->save($caseObj);
            }
        }

        /**
         * Getting all the cases that are awaiting review from Signifyd
         */
        $inReviewCases = $this->caseRetryObj->getRetryCasesByStatus(Casedata::IN_REVIEW_STATUS);

        foreach ($inReviewCases as $case) {
            $message = "Signifyd: preparing for review case no: {$case['order_increment']}";
            $this->logger->debug($message, ['entity' => $case]);

            $this->caseRetryObj->processInReviewCase($case, $this->getOrder($case['order_increment']));
        }

        /**
         * Getting all the cases that need processing after the response was received
         */
        $inProcessingCases = $this->caseRetryObj->getRetryCasesByStatus(Casedata::PROCESSING_RESPONSE_STATUS);

        foreach ($inProcessingCases as $case) {
            $message = "Signifyd: preparing for process case no: {$case['order_increment']}";
            $this->logger->debug($message, ['entity' => $case]);

            $this->caseRetryObj->processResponseStatus($case, $this->getOrder($case['order_increment']));
        }

        $this->logger->debug("Main retry method ended");

        return $this;
    }

    /**
     * @param $incrementId
     * @return \Magento\Sales\Model\Order
     */
    public function getOrder($incrementId)
    {
        $order = $this->orderFactory->create();
        $this->orderResourceModel->load($order, $incrementId, 'increment_id');

        if ($order->getPayment()->getMethod() == 'stripe_payments') {
            $this->reInitStripe($order);
        }

        return $order;
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     * @return bool
     */
    public function reInitStripe(\Magento\Sales\Model\Order $order)
    {
        if (class_exists(\StripeIntegration\Payments\Model\Config::class) === false) {
            return false;
        }

        if ($this->stripeConfig === null) {
            $this->stripeConfig = $this->_objectManager->get(\StripeIntegration\Payments\Model\Config::class);
        }

        if (version_compare(\StripeIntegration\Payments\Model\Config::$moduleVersion, '1.8.8') >= 0 &&
            method_exists($this->stripeConfig, 'reInitStripe')) {
            $this->stripeConfig->reInitStripe($order->getStoreId(), $order->getBaseCurrencyCode(), null);
        }

        return true;
    }
}
