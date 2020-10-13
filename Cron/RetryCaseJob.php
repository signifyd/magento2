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
use Signifyd\Connect\Helper\ConfigHelper;
use Signifyd\Connect\Helper\Retry;
use Signifyd\Connect\Model\Casedata;
use Signifyd\Connect\Model\ResourceModel\Casedata as CasedataResourceModel;
use Signifyd\Connect\Model\CasedataFactory;
use Magento\Framework\App\ResourceConnection;

class RetryCaseJob
{
    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @var PurchaseHelper
     */
    protected $purchaseHelper;

    /**
     * @var ConfigHelper
     */
    protected $configHelper;


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
     * @var CasedataFactory
     */
    protected $casedataFactory;

    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * @var \StripeIntegration\Payments\Model\Config
     */
    protected $stripeConfig;

    /**
     * RetryCaseJob constructor.
     * @param ObjectManagerInterface $objectManager
     * @param PurchaseHelper $purchaseHelper
     * @param ConfigHelper $configHelper
     * @param Logger $logger
     * @param Retry $caseRetryObj
     * @param OrderResourceModel $orderResourceModel
     * @param OrderFactory $orderFactory
     * @param CasedataResourceModel $casedataResourceModel
     * @param CasedataFactory $casedataFactory
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        PurchaseHelper $purchaseHelper,
        ConfigHelper $configHelper,
        Logger $logger,
        Retry $caseRetryObj,
        OrderResourceModel $orderResourceModel,
        OrderFactory $orderFactory,
        CasedataResourceModel $casedataResourceModel,
        CasedataFactory $casedataFactory,
        ResourceConnection $resourceConnection
    ) {
        $this->objectManager = $objectManager;
        $this->purchaseHelper = $purchaseHelper;
        $this->configHelper = $configHelper;
        $this->logger = $logger;
        $this->caseRetryObj = $caseRetryObj;
        $this->orderResourceModel = $orderResourceModel;
        $this->orderFactory = $orderFactory;
        $this->casedataResourceModel = $casedataResourceModel;
        $this->casedataFactory = $casedataFactory;
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * Entry point to Cron job
     */
    public function execute()
    {
        $this->logger->debug("Main retry method called");

        $asyncWaitingCases = $this->caseRetryObj->getRetryCasesByStatus(Casedata::ASYNC_WAIT);

        /** @var \Signifyd\Connect\Model\Casedata $case */
        foreach ($asyncWaitingCases as $case) {
            $this->logger->debug(
                "Signifyd: preparing for send case no: {$case->getOrderIncrement()}",
                ['entity' => $case]
            );

            $caseModel = $this->purchaseHelper->processOrderData($case->getOrder());
            $avsCode = $caseModel->getPurchase()->getAvsResponseCode();
            $cvvCode = $caseModel->getPurchase()->getCvvResponseCode();
            $retries = $case->getData('retries');

            if ($retries >= 5 || empty($avsCode) == false && empty($cvvCode) == false) {
                try {
                    $this->resourceConnection->getConnection()->beginTransaction();
                    $this->casedataResourceModel->loadForUpdate($case, $case->getId());

                    $case->setMagentoStatus(Casedata::WAITING_SUBMISSION_STATUS);
                    $case->setUpdated();

                    $this->casedataResourceModel->save($case);
                    $this->resourceConnection->getConnection()->commit();
                } catch (\Exception $e) {
                    $this->resourceConnection->getConnection()->rollBack();
                    $this->logger->error('Failed to save case data to database: ' . $e->getMessage());
                }
            }
        }

        /**
         * Getting all the cases that were not submitted to Signifyd
         */
        $waitingCases = $this->caseRetryObj->getRetryCasesByStatus(Casedata::WAITING_SUBMISSION_STATUS);

        /** @var \Signifyd\Connect\Model\Casedata $case */
        foreach ($waitingCases as $case) {
            $this->logger->debug(
                "Signifyd: preparing for send case no: {$case['order_increment']}",
                ['entity' => $case]
            );

            $this->reInitStripe($case->getOrder());

            $caseModel = $this->purchaseHelper->processOrderData($case->getOrder());
            $investigationId = $this->purchaseHelper->postCaseToSignifyd($caseModel, $case->getOrder());

            if (empty($investigationId) === false) {
                try {
                    $this->resourceConnection->getConnection()->beginTransaction();
                    $this->casedataResourceModel->loadForUpdate($case, $case->getId());

                    $case->setCode($investigationId);
                    $case->setMagentoStatus(Casedata::IN_REVIEW_STATUS);
                    $case->setUpdated();

                    $this->casedataResourceModel->save($case);
                    $this->resourceConnection->getConnection()->commit();
                } catch (\Exception $e) {
                    $this->resourceConnection->getConnection()->rollBack();
                    $this->logger->error('Failed to save case data to database: ' . $e->getMessage());
                }
            }
        }

        /**
         * Getting all the cases that are awaiting review from Signifyd
         */
        $inReviewCases = $this->caseRetryObj->getRetryCasesByStatus(Casedata::IN_REVIEW_STATUS);

        foreach ($inReviewCases as $case) {
            $this->logger->debug(
                "Signifyd: preparing for review case no: {$case['order_increment']}",
                ['entity' => $case]
            );

            $this->reInitStripe($case->getOrder());

            try {
                $response = $this->configHelper->getSignifydApi($case)->getCase($case->getCode());

                $this->resourceConnection->getConnection()->beginTransaction();
                $this->casedataResourceModel->loadForUpdate($case, $case->getId());

                $case->updateCase($response);
                $case->updateOrder();

                $this->casedataResourceModel->save($case);
                $this->orderResourceModel->save($case->getOrder());
                $this->resourceConnection->getConnection()->commit();
            } catch (\Exception $e) {
                $this->resourceConnection->getConnection()->rollBack();
                $this->logger->error('Failed to save case data to database: ' . $e->getMessage());
            }
        }

        /**
         * Getting all the cases that need processing after the response was received
         */
        $inProcessingCases = $this->caseRetryObj->getRetryCasesByStatus(Casedata::PROCESSING_RESPONSE_STATUS);

        foreach ($inProcessingCases as $case) {
            $this->logger->debug(
                "Signifyd: preparing for process case no: {$case['order_increment']}",
                ['entity' => $case]
            );

            $this->reInitStripe($case->getOrder());

            try {
                $this->resourceConnection->getConnection()->beginTransaction();
                $this->casedataResourceModel->loadForUpdate($case, $case->getId());

                $case->updateOrder();

                $this->casedataResourceModel->save($case);
                $this->orderResourceModel->save($case->getOrder());
                $this->resourceConnection->getConnection()->commit();
            } catch (\Exception $e) {
                $this->resourceConnection->getConnection()->rollBack();
                $this->logger->error('Failed to save case data to database: ' . $e->getMessage());
            }
        }

        $this->logger->debug("Main retry method ended");
    }

    /**
     * On background tasks Stripe must be reinitialized
     *
     * @param \Magento\Sales\Model\Order $order
     * @return bool
     */
    public function reInitStripe(\Magento\Sales\Model\Order $order)
    {
        if (class_exists(\StripeIntegration\Payments\Model\Config::class) === false) {
            return false;
        }

        if ($this->stripeConfig === null) {
            $this->stripeConfig = $this->objectManager->get(\StripeIntegration\Payments\Model\Config::class);
        }

        if (version_compare(\StripeIntegration\Payments\Model\Config::$moduleVersion, '1.8.8') >= 0 &&
            method_exists($this->stripeConfig, 'reInitStripe')) {
            $this->stripeConfig->reInitStripe($order->getStoreId(), $order->getBaseCurrencyCode(), null);
        }

        return true;
    }
}
