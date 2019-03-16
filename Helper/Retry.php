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

    public function __construct(
        Context $context,
        Casedata $caseData,
        ConfigHelper $configHelper,
        ObjectManagerInterface $objectManager
    )
    {
        parent::__construct($context);

        $this->caseData = $caseData;
        $this->configHelper = $configHelper;
        $this->objectManager = $objectManager;
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
        $casesCollection->addFieldToFilter('updated', array('gteq' => $from));
        $casesCollection->addFieldToFilter('magento_status', array('eq' => $status));
        $casesCollection->addFieldToFilter('retries', array('lt' => count($retryTimes)));
        $casesCollection->addExpressionFieldToSelect('seconds_after_update',
            "TIME_TO_SEC(TIMEDIFF('{$current}', updated))", array('updated'));

        $casesToRetry = array();

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
        $retryTimes = array();

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
            $caseData['request'] = $this->configHelper->getSignifydApi($case)->getCase($case->getCode());
            $caseData['case'] = $case;
            $caseData['order'] = $order;
            /** @var \Signifyd\Connect\Model\Casedata $caseObj */
            $caseObj = $this->objectManager->create('Signifyd\Connect\Model\Casedata');
            $caseObj->updateCase($caseData);
            return true;
        } catch (\Exception $e) {
            $this->logger->critical($e->__toString());
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
        $orderAction = array('action' => null, 'reason' => null);
        $negativeAction = $case->getNegativeAction();
        $positiveAction = $case->getPositiveAction();

        if ($case->getGuarantee() == 'DECLINED' && $negativeAction != 'nothing') {
            $orderAction = array("action" => $negativeAction, "reason" => "guarantee declined");
        } else {
            if ($case->getGuarantee() == 'APPROVED' && $positiveAction != 'nothing') {
                $orderAction = array("action" => $positiveAction, "reason" => "guarantee approved");
            }
        }
        $caseData = array('order' => $order);
        /** @var \Signifyd\Connect\Model\Casedata $caseObj */
        $caseObj = $this->objectManager->create('Signifyd\Connect\Model\Casedata');
        $caseObj->updateOrder($caseData, $orderAction, $case);
    }
}