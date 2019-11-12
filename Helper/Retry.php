<?php
/**
 * Copyright 2015 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Signifyd\Connect\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\ObjectManagerInterface;
use Signifyd\Connect\Model\Casedata;
use Signifyd\Connect\Logger\Logger;

class Retry extends AbstractHelper
{
    /**
     * @var Casedata
     */
    protected $caseData;

    /**
     * @var ConfigHelper
     */
    protected $configHelper;

    /**
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @var Logger
     */
    protected $logger;

    public function __construct(
        Context $context,
        Casedata $caseData,
        ConfigHelper $configHelper,
        ObjectManagerInterface $objectManager,
        Logger $logger
    ) {
        parent::__construct($context);

        $this->caseData = $caseData;
        $this->configHelper = $configHelper;
        $this->objectManager = $objectManager;
        $this->logger = $logger;
    }

    /**
     * @param $status
     * @return mixed
     */
    public function getRetryCasesByStatus($status)
    {
        $retryTimes = $this->calculateRetryTimes();

        $time = time();
        $lastTime = $time - (end($retryTimes) + 60*60*24);
        $current = date('Y-m-d H:i:s', $time);
        $from = date('Y-m-d H:i:s', $lastTime);

        $casesCollection = $this->caseData->getCollection();
        $casesCollection->addFieldToFilter('updated', ['gteq' => $from]);
        $casesCollection->addFieldToFilter('magento_status', ['eq' => $status]);
        $casesCollection->addFieldToFilter('retries', ['lt' => count($retryTimes)]);
        $casesCollection->addExpressionFieldToSelect(
            'seconds_after_update',
            "TIME_TO_SEC(TIMEDIFF('{$current}', updated))",
            ['updated']
        );

        $casesToRetry = [];

        foreach ($casesCollection->getItems() as $case) {
            $retries = $case->getData('retries');
            $secondsAfterUpdate = $case->getData('seconds_after_update');

            if ($secondsAfterUpdate > $retryTimes[$retries]) {
                $casesToRetry[$case->getId()] = $case;
                $case->setData('retries', $retries+1);
                $case->save();
            }
        }

        return $casesToRetry;
    }

    /**
     * Retry times calculated from last update
     *
     * @return array
     */
    public function calculateRetryTimes()
    {
        $retryTimes = [];

        for ($retry = 0; $retry < 15; $retry++) {
            // Increment retry times exponentially
            $retryTimes[$retry] = 20 * pow(2, $retry);
            // Increment should not be greater than one day
            $retryTimes[$retry] = $retryTimes[$retry] > 86400 ? 86400 : $retryTimes[$retry];
            // Sum retry time to previous, calculating total time to wait from last update
            $retryTimes[$retry] += isset($retryTimes[$retry-1]) ? $retryTimes[$retry-1] : 0;
        }

        return $retryTimes;
    }

    /**
     * Process the cases that are in review
     *
     * @param $case
     * @param $order
     * @return bool
     */
    public function processInReviewCase($case, $order)
    {
        if (empty($case->getCode())) {
            return false;
        }

        try {
            $caseData['response'] = $this->configHelper->getSignifydApi($case)->getCase($case->getCode());
            $caseData['case'] = $case;
            $caseData['order'] = $order;
            /** @var \Signifyd\Connect\Model\Casedata $caseObj */
            $caseObj = $this->objectManager->create('Signifyd\Connect\Model\Casedata');
            $caseObj->updateCase($caseData);
            return true;
        } catch (\Exception $e) {
            $this->logger->critical($e->__toString(), ['entity' => $order]);
            return false;
        }
    }

    /**
     * @param \Signifyd\Connect\Model\Casedata $case
     * @param $order
     * @return mixed
     */
    public function processResponseStatus($case, $order)
    {
        $negativeAction = $case->getNegativeAction();
        $positiveAction = $case->getPositiveAction();

        switch ($case->getGuarantee()) {
            case 'DECLINED':
                $orderAction = ["action" => $negativeAction, "reason" => "guarantee declined"];
                break;

            case 'APPROVED':
                $orderAction = ["action" => $positiveAction, "reason" => "guarantee approved"];
                break;

            default:
                $orderAction = ['action' => null, 'reason' => null];
        }

        $caseData = ['order' => $order];
        /** @var \Signifyd\Connect\Model\Casedata $caseObj */
        $caseObj = $this->objectManager->create('Signifyd\Connect\Model\Casedata');
        $caseObj->updateOrder($caseData, $orderAction, $case);
    }
}
