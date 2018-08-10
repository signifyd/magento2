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
use Magento\Sales\Model\Order;

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

        if (isset($request->testInvestigation)) {
            $case->setEntriesText(serialize(array('testInvestigation' => $request->testInvestigation)));
        }

        try{
            $order->getResource()->save($order);
            $this->getResource()->save($case);
            $this->updateOrder($caseData, $orderAction, $case);
            $this->_logger->info('Case was saved, id:' . $case->getIncrementId());
        } catch (\Exception $e){
            $this->_logger->critical($e->__toString());
            return false;
        }


        return true;
    }

    /**
     * @param array $caseData
     * @param array $orderAction
     * @param \Signifyd\Connect\Model\Casedata $case
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
                        $order->hold()->getResource()->save($order);
                        $case->setMagentoStatus(CaseRetry::COMPLETED_STATUS)
                            ->setUpdated(strftime('%Y-%m-%d %H:%M:%S', time()));
                        $case->getResource()->save($case);
                    } catch (\Exception $e){
                        $this->_logger->debug($e->__toString());
                        return false;
                    }
                } else {
                    $notHoldableStates = [
                        Order::STATE_CANCELED,
                        Order::STATE_PAYMENT_REVIEW,
                        Order::STATE_COMPLETE,
                        Order::STATE_CLOSED,
                        Order::STATE_HOLDED
                    ];

                    if (in_array($order->getState(), $notHoldableStates)) {
                        $reason = "order is on {$order->getState()} state";
                    } elseif ($order->getActionFlag(Order::ACTION_FLAG_HOLD) === false) {
                        $reason = "order action flag is set to do not hold";
                    } else {
                        $reason = "unknown reason";
                    }

                    $this->_logger->debug("Order {$order->getIncrementId()} can not be held because {$reason}");
                    
                    $case->setMagentoStatus(CaseRetry::COMPLETED_STATUS)
                        ->setUpdated(strftime('%Y-%m-%d %H:%M:%S', time()));
                    $case->getResource()->save($case);
                }
                break;

            case "unhold":
                if ($order->canUnhold()) {
                    $this->_logger->debug('Unhold order action');
                    try{
                        $order->unhold()->getResource()->save($order);
                        $case->setMagentoStatus(CaseRetry::COMPLETED_STATUS)
                            ->setUpdated(strftime('%Y-%m-%d %H:%M:%S', time()));
                        $case->getResource()->save($case);
                    } catch (\Exception $e){
                        $this->_logger->debug($e->__toString());
                        return false;
                    }
                } else {
                    if ($order->getState() != Order::STATE_HOLDED) {
                        $reason = "order is not holded";
                    } elseif ($order->isPaymentReview()) {
                        $reason = 'order is in payment review';
                    } elseif ($order->getActionFlag(Order::ACTION_FLAG_UNHOLD) === false) {
                        $reason = "order action flag is set to do not unhold";
                    } else {
                        $reason = "unknown reason";
                    }

                    $this->_logger->debug(
                        "Order {$order->getIncrementId()} ({$order->getState()} > {$order->getStatus()}) " .
                        "can not be unheld because {$reason}. " .
                        "Case status: {$case->getSignifydStatus()}"
                    );

                    $case->setMagentoStatus(CaseRetry::COMPLETED_STATUS)
                        ->setUpdated(strftime('%Y-%m-%d %H:%M:%S', time()));
                    $case->getResource()->save($case);
                }
                break;

            case "cancel":
                // Can't cancel if order is on hold
                if ($order->canUnhold()) {
                    $order = $order->unhold();
                }

                if ($order->canCancel()) {
                    try {
                        $order->cancel()->getResource()->save($order);
                        $case->setMagentoStatus(CaseRetry::COMPLETED_STATUS)
                            ->setUpdated(strftime('%Y-%m-%d %H:%M:%S', time()));
                        $case->getResource()->save($case);
                    } catch (\Exception $e) {
                        $this->_logger->debug($e->__toString());
                        return false;
                    }
                } else {
                    $notCancelableStates = [
                        Order::STATE_CANCELED,
                        Order::STATE_PAYMENT_REVIEW,
                        Order::STATE_COMPLETE,
                        Order::STATE_CLOSED,
                        Order::STATE_HOLDED
                    ];

                    if (in_array($order->getState(), $notCancelableStates)) {
                        $reason = "order is on {$order->getState()} state";
                    } elseif (!$order->canReviewPayment() && $order->canFetchPaymentReviewUpdate()) {
                        $reason = "payment review issues";
                    } elseif ($order->getActionFlag(Order::ACTION_FLAG_CANCEL) === false) {
                        $reason = "order action flag is set to do not cancel";
                    } else {
                        $allInvoiced = true;
                        foreach ($order->getAllItems() as $item) {
                            if ($item->getQtyToInvoice()) {
                                $allInvoiced = false;
                                break;
                            }
                        }
                        if ($allInvoiced) {
                            $reason = "all items are invoiced";
                        } else {
                            $reason = "unknown reason";
                        }
                    }

                    $this->_logger->debug("Order {$order->getIncrementId()} can not be canceled because {$reason}");

                    $case->setMagentoStatus(CaseRetry::COMPLETED_STATUS)
                        ->setUpdated(strftime('%Y-%m-%d %H:%M:%S', time()));
                    $case->getResource()->save($case);
                }
                break;

            // Do nothing - don't put a break on this case, must get on "null" to complete Signifyd case
            case 'nothing':
                unset($orderAction['action']);
                break;

            case null:
                try {
                    $case->setMagentoStatus(CaseRetry::COMPLETED_STATUS)
                        ->setUpdated(strftime('%Y-%m-%d %H:%M:%S', time()));
                    $case->getResource()->save($case);
                } catch (\Exception $e) {
                    $this->_logger->debug($e->__toString());
                    return false;
                }
                break;
        }

        if (!is_null($orderAction['action'])) {
            $order->addStatusHistoryComment("Signifyd set status to {$orderAction["action"]} because {$orderAction["reason"]}");
            $order->getResource()->save($order);
        }

        return true;
    }

    /**
     * @param $caseData
     * @throws \Magento\Framework\Exception\LocalizedException
     * @return array
     */
    protected function handleStatusChange($caseData)
    {
        if ($caseData['request']->reviewDisposition == 'FRAUDULENT') {
            return array("action" => "hold", "reason" => "review returned FRAUDULENT");
        } else {
            if ($caseData['request']->reviewDisposition == 'GOOD') {
                return array("action" => "unhold", "reason" => "review returned GOOD");
            }
        }
        return null;
    }

    /**
     * @param $caseData
     * @throws \Magento\Framework\Exception\LocalizedException
     * @return array
     */
    protected function handleGuaranteeChange($caseData)
    {
        if (!isset($caseData['case']) || !$caseData['case'] instanceof \Signifyd\Connect\Model\Casedata) {
            return null;
        }

        $negativeAction = $caseData['case']->getNegativeAction();
        $positiveAction = $caseData['case']->getPositiveAction();
        
        $this->_logger->debug("Signifyd: Positive Action: " . $positiveAction);
        $request = $caseData['request'];
        switch ($request->guaranteeDisposition){
            case "DECLINED":
                return array("action" => $negativeAction, "reason" => "guarantee declined");
                break;
            case "APPROVED":
                return array("action" => $positiveAction, "reason" => "guarantee approved");
                break;
            default:
                $this->_logger->debug("Signifyd: Unknown guaranty: " . $request->guaranteeDisposition);
                break;
        }

        return null;
    }

    /**
     * @param null $index
     * @return array|mixed|null
     */
    public function getEntries($index = null)
    {
        $entries = $this->getData('entries_text');

        if (!empty($entries)) {
            @$entries = unserialize($entries);
        }

        if (!is_array($entries)) {
            $entries = array();
        }

        if (!empty($index)) {
            return isset($entries[$index]) ? $entries[$index] : null;
        }

        return $entries;
    }

    public function setEntries($index, $value = null)
    {
        if (is_array($index)) {
            $entries = $index;
        } elseif (is_string($index)) {
            $entries = $this->getEntries();
            $entries[$index] = $value;
        }

        @$entries = serialize($entries);
        $this->setData('entries_text', $entries);

        return $this;
    }

    public function isHoldReleased()
    {
        $holdReleased = $this->getEntries('hold_released');
        return ($holdReleased == 1) ? true : false;
    }
    
    public function getPositiveAction()
    {
        if ($this->isHoldReleased()) {
            return 'nothing';
        } else {
            return $this->_coreConfig->getValue('signifyd/advanced/guarantee_positive_action', 'store');
        }
    }

    public function getNegativeAction()
    {
        if ($this->isHoldReleased()) {
            return 'nothing';
        } else {
            return $this->_coreConfig->getValue('signifyd/advanced/guarantee_negative_action', 'store');
        }
    }
}
