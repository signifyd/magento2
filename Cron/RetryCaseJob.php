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
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\StoreManagerInterface;

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
     * @var Emulation
     */
    protected $emulation;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManagerInterface;

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
     * @param Emulation $emulation
     * @param StoreManagerInterface $storeManagerInterface
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
        ResourceConnection $resourceConnection,
        Emulation $emulation,
        StoreManagerInterface $storeManagerInterface
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
        $this->emulation = $emulation;
        $this->storeManagerInterface = $storeManagerInterface;
    }

    /**
     * Entry point to Cron job
     */
    public function execute()
    {
        $this->emulation->startEnvironmentEmulation(0, 'adminhtml');
        $this->logger->debug("CRON: Main retry method called");

        $asyncWaitingCases = $this->caseRetryObj->getRetryCasesByStatus(Casedata::ASYNC_WAIT);

        /** @var \Signifyd\Connect\Model\Casedata $case */
        foreach ($asyncWaitingCases as $case) {
            $this->storeManagerInterface->setCurrentStore($case->getOrder()->getStore()->getStoreId());

            $this->logger->debug(
                "CRON: preparing for send case no: {$case->getOrderIncrement()}",
                ['entity' => $case]
            );

            $case->getOrder()->setData('origin_store_code', $case->getData('origin_store_code'));
            $caseModel = $this->purchaseHelper->processOrderData($case->getOrder());
            $avsCode = $caseModel['transactions'][0]['avsResponseCode'];
            $cvvCode = $caseModel['transactions'][0]['cvvResponseCode'];
            $retries = $case->getData('retries');

            if ($retries >= 5 || empty($avsCode) == false && empty($cvvCode) == false) {
                try {
                    $this->casedataResourceModel->loadForUpdate($case, (string) $case->getData('entity_id'));

                    $case->setMagentoStatus(Casedata::WAITING_SUBMISSION_STATUS);
                    $case->setUpdated();

                    $this->casedataResourceModel->save($case);
                } catch (\Exception $e) {
                    $this->logger->error('CRON: Failed to save case data to database: ' . $e->getMessage());
                }
            }
        }

        /**
         * Getting all the cases that were not submitted to Signifyd
         */
        $waitingCases = $this->caseRetryObj->getRetryCasesByStatus(Casedata::WAITING_SUBMISSION_STATUS);

        /** @var \Signifyd\Connect\Model\Casedata $case */
        foreach ($waitingCases as $case) {
            $this->storeManagerInterface->setCurrentStore($case->getOrder()->getStore()->getStoreId());

            $this->logger->debug(
                "CRON: preparing for send case no: {$case['order_increment']}",
                ['entity' => $case]
            );

            $this->reInitStripe($case->getOrder());

            try {
                $this->casedataResourceModel->loadForUpdate($case, (string) $case->getData('entity_id'));

                $caseModel = $this->purchaseHelper->processOrderData($case->getOrder());
                /** @var \Signifyd\Core\Response\SaleResponse $caseResponse */
                $caseResponse = $this->purchaseHelper->postCaseToSignifyd($caseModel, $case->getOrder());
                $investigationId = $caseResponse->getSignifydId();

                if (empty($investigationId) === false) {
                    $case->setCode($investigationId);
                    $case->setMagentoStatus(Casedata::IN_REVIEW_STATUS);
                    $case->setUpdated();
                    $this->casedataResourceModel->save($case);
                }
            } catch (\Exception $e) {
                $this->logger->error('CRON: Failed to save case data to database: ' . $e->getMessage());
            }
        }

        /**
         * Getting all the cases that are awaiting review from Signifyd
         */
        $inReviewCases = $this->caseRetryObj->getRetryCasesByStatus(Casedata::IN_REVIEW_STATUS);

        foreach ($inReviewCases as $case) {
            $this->storeManagerInterface->setCurrentStore($case->getOrder()->getStore()->getStoreId());

            $this->logger->debug(
                "CRON: preparing for review case no: {$case['order_increment']}",
                ['entity' => $case]
            );

            $this->reInitStripe($case->getOrder());

            try {
                $response = $this->configHelper->getSignifydCaseApi($case)->getCase($case->getData('code'));

                $this->casedataResourceModel->loadForUpdate($case, (string) $case->getData('entity_id'));

                $currentCaseHash = sha1(implode(',', $case->getData()));
                $case->updateCase($response);
                $newCaseHash = sha1(implode(',', $case->getData()));

                if ($currentCaseHash == $newCaseHash) {
                    $this->logger->info(
                        "CRON: Case {$case->getId()} already update with this data, no action will be taken"
                    );

                    // Triggering case save to unlock case
                    $this->casedataResourceModel->save($case);

                    continue;
                }

                $case->updateOrder();

                $this->casedataResourceModel->save($case);
            } catch (\Exception $e) {
                // Triggering case save to unlock case
                if ($case instanceof \Signifyd\Connect\Model\Casedata) {
                    $this->casedataResourceModel->save($case);
                }

                $this->logger->error('CRON: Failed to save case data to database: ' . $e->getMessage());
            }
        }

        $this->logger->debug("CRON: Main retry method ended");
        $this->emulation->stopEnvironmentEmulation();
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
