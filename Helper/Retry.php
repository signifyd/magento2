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
    protected $caseData;
    protected $api;
    protected $objectManager;

    public function __construct(
        Context $context,
        Casedata $caseData,
        SignifydAPIMagento $api,
        ObjectManagerInterface $objectManager
    )
    {
        parent::__construct($context);
        $this->caseData = $caseData;
        $this->api = $api;
        $this->objectManager = $objectManager;
    }

    /**
     * @param $status
     * @return mixed
     */
    public function getRetryCasesByStatus($status)
    {
        $time = time();
        $lastTime = $time -  60*60*24*7; // not longer than 7 days
        $firstTime = $time -  60*30; // longer than last 30 minuted
        $from = date('Y-m-d H:i:s', $lastTime);
        $to = date('Y-m-d H:i:s', $firstTime);

        $casesCollection = $this->caseData->getCollection();
        $casesCollection->addFieldToFilter('updated', array('from' => $from, 'to' => $to));
        $casesCollection->addFieldToFilter('magento_status', array('eq' => $status));

        return $casesCollection;
    }

    /**
     * Process the cases that are in review
     * @param $case
     * @return bool
     */
    public function processInReviewCase($case, $order)
    {
        if(empty($case->getCode())) return false;
        try {
            $caseData['request'] = $this->api->getCase($case->getCode());
            $caseData['case'] = $case;
            $caseData['order'] = $order;
            $caseObj = $this->objectManager->create('Signifyd\Connect\Model\Casedata');
            $caseObj->updateCase($caseData);
            return true;
        } catch (\Exception $e) {
            $this->_logger->critical($e->__toString());
            return false;
        }
    }

    /**
     * @param $case
     * @param $order
     * @return mixed
     */
    public function processResponseStatus($case, $order)
    {
        $orderAction = array('action' => null, 'reason' => null);
        $negativeAction = $this->scopeConfig->getValue('signifyd/advanced/guarantee_negative_action');
        $positiveAction = $this->scopeConfig->getValue('signifyd/advanced/guarantee_positive_action');

        if ($case->getGuarantee() == 'DECLINED' && $negativeAction != 'nothing') {
            $orderAction = array("action" => $negativeAction, "reason" => "guarantee declined");
        } else {
            if ($case->getGuarantee() == 'APPROVED' && $positiveAction != 'nothing') {
                $orderAction = array("action" => $positiveAction, "reason" => "guarantee approved");
            }
        }
        $caseData = array('order' => $order);
        $caseObj = $this->objectManager->create('Signifyd\Connect\Model\Casedata');
        $result = $caseObj->updateOrder($caseData, $orderAction, $case);
        return $result;
    }
}