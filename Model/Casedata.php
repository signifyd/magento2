<?php
/**
 * Copyright 2015 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Signifyd\Connect\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;

/**
 * ORM model declaration for case data
 */
class Casedata extends AbstractModel
{
    protected $_coreConfig;

    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $coreConfig
    )
    {
        $this->_coreConfig = $coreConfig;
        parent::__construct($context, $registry);
    }

    /**
     * Constructor
     *
     * @return void
     */
    protected function _construct()
    {
        parent::_construct();
        $this->_init('Signifyd\Connect\Model\ResourceModel\Casedata');
    }

    /**
     * @param $caseData
     * @return bool
     */
    public function updateCase($caseData)
    {
        /** @var $case \Signifyd\Connect\Model\Casedata */
        $case = $caseData['case'];
        $request = $caseData['request'];
        $order = $caseData['order'];
        $case->setMagentoStatus(CaseRetry::PROCESSING_RESPONSE_STATUS);

        $orderAction = array("action" => null, "reason" => '');
        if (isset($request->score) && $case->getScore() != $request->score) {
            $case->setScore($request->score);
            $order->setSignifydScore($request->score);
            $orderAction = $this->handleScoreChange($caseData) ?: $orderAction;
        }

        if (isset($request->status) && $case->getSignifydStatus() != $request->status) {
            $case->setSignifydStatus($request->status);
            $orderAction = $this->handleStatusChange($caseData) ?: $orderAction;
        }

        if (isset($request->guaranteeDisposition) && $case->getGuarantee() != $request->guaranteeDisposition) {
            $case->setGuarantee($request->guaranteeDisposition);
            $order->setSignifydGuarantee($request->guaranteeDisposition);
            $orderAction = $this->handleGuaranteeChange($caseData) ?: $orderAction;
        }

        $case->setCode($request->caseId);
        $case->setUpdated(strftime('%Y-%m-%d %H:%M:%S', time()));
        $order->setSignifydCode($request->caseId);

        try{
            $order->save();
            $case->save();
            $this->updateOrder($caseData, $orderAction, $case);
        } catch (\Exception $e){
            $this->_logger->critical($e->__toString());
            return false;
        }


        return true;
    }

    /**
     * @param array $caseData
     * @param string $orderAction
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function updateOrder($caseData, $orderAction, $case)
    {
        /** @var $order \Magento\Sales\Model\Order */
        $order = $caseData['order'];
        switch ($orderAction["action"]) {
            case "hold":
                if ($order->canHold()) {
                    try {
                        $order->hold()->save();
                        $case->setMagentoStatus(CaseRetry::COMPLETED_STATUS)
                            ->setUpdated(strftime('%Y-%m-%d %H:%M:%S', time()));
                        $case->save();
                    } catch (\Exception $e){
                        $this->_logger->debug($e->__toString());
                        return false;
                    }
                } else {
                    $case->setMagentoStatus(CaseRetry::COMPLETED_STATUS)
                        ->setUpdated(strftime('%Y-%m-%d %H:%M:%S', time()));
                    $case->save();
                }
                break;
            case "unhold":
                if ($order->canUnhold()) {
                    try{
                        $order->unhold()->save();
                        $case->setMagentoStatus(CaseRetry::COMPLETED_STATUS)
                            ->setUpdated(strftime('%Y-%m-%d %H:%M:%S', time()));
                        $case->save();
                    } catch (\Exception $e){
                        $this->_logger->debug($e->__toString());
                        return false;
                    }
                } else {
                    $case->setMagentoStatus(CaseRetry::COMPLETED_STATUS)
                        ->setUpdated(strftime('%Y-%m-%d %H:%M:%S', time()));
                    $case->save();
                }
                break;
            case "cancel":
                // Can't cancel if order is on hold
                if ($order->canUnhold()) {
                    $order = $order->unhold();
                }
                if ($order->canCancel()) {
                    try {
                        $order->cancel()->save();
                        $case->setMagentoStatus(CaseRetry::COMPLETED_STATUS)
                            ->setUpdated(strftime('%Y-%m-%d %H:%M:%S', time()));
                        $case->save();
                    } catch (\Exception $e) {
                        $this->_logger->debug($e->__toString());
                        return false;
                    }
                } else {
                    $case->setMagentoStatus(CaseRetry::COMPLETED_STATUS)
                        ->setUpdated(strftime('%Y-%m-%d %H:%M:%S', time()));
                    $case->save();
                }
                break;
            case null:
                try {
                    $case->setMagentoStatus(CaseRetry::COMPLETED_STATUS)
                        ->setUpdated(strftime('%Y-%m-%d %H:%M:%S', time()));
                    $case->save();
                } catch (\Exception $e) {
                    $this->_logger->debug($e->__toString());
                    return false;
                }
                break;
        }
        if(!is_null($orderAction['action'])){
            $order->addStatusHistoryComment("Signifyd set status to {$orderAction["action"]} because {$orderAction["reason"]}");
            $order->save();
        }

        return true;
    }

    /**
     * @param $caseData
     * @throws \Magento\Framework\Exception\LocalizedException
     * @return string
     */
    protected function handleScoreChange($caseData)
    {
        $threshHold = (int)$this->_coreConfig->getValue('signifyd/advanced/hold_orders_threshold');
        $holdBelowThreshold = $this->_coreConfig->getValue('signifyd/advanced/hold_orders');
        if ($holdBelowThreshold && $caseData['request']->score <= $threshHold) {
            return array("action" => "hold", "reason" => "score threshold failure");
        }
        return null;
    }

    /**
     * @param $caseData
     * @throws \Magento\Framework\Exception\LocalizedException
     * @return string
     */
    protected function handleStatusChange($caseData)
    {
        $holdBelowThreshold = $this->_coreConfig->getValue('signifyd/advanced/hold_orders');
        if ($holdBelowThreshold && $caseData['request']->reviewDisposition == 'FRAUDULENT') {
            return array("action" => "hold", "reason" => "review returned FRAUDULENT");
        } else {
            if ($holdBelowThreshold && $caseData['request']->reviewDisposition == 'GOOD') {
                return array("action" => "unhold", "reason" => "review returned GOOD");
            }
        }
        return null;
    }

    /**
     * @param $caseData
     * @throws \Magento\Framework\Exception\LocalizedException
     * @return string
     */
    protected function handleGuaranteeChange($caseData)
    {
        $negativeAction = $this->_coreConfig->getValue('signifyd/advanced/guarantee_negative_action');
        $positiveAction = $this->_coreConfig->getValue('signifyd/advanced/guarantee_positive_action');

        $request = $caseData['request'];
        if ($request->guaranteeDisposition == 'DECLINED' && $negativeAction != 'nothing') {
            return array("action" => $negativeAction, "reason" => "guarantee declined");
        } else {
            if ($request->guaranteeDisposition == 'APPROVED' && $positiveAction != 'nothing') {
                return array("action" => $positiveAction, "reason" => "guarantee approved");
            }
        }
        return null;
    }

}
