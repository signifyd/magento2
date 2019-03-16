<?php
/**
 * Copyright 2015 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Signifyd\Connect\Model;

use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Framework\ObjectManagerInterface;
use Magento\Sales\Model\Order;
use Signifyd\Connect\Logger\Logger;

/**
 * ORM model declaration for case data
 */
class Casedata extends AbstractModel
{
    /**
     * @var \Signifyd\Connect\Helper\ConfigHelper
     */
    protected $configHelper;

    /**
     * @var InvoiceService
     */
    private $invoiceService;

    /**
     * @var InvoiceSender
     */
    protected $invoiceSender;

    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $orderFactory;

    /**
     * @var \Magento\Sales\Model\Order
     */
    protected $order;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * Casedata constructor.
     * @param Context $context
     * @param Registry $registry
     * @param \Signifyd\Connect\Helper\ConfigHelper $configHelper
     * @param InvoiceService $invoiceService
     * @param Logger
     */
    public function __construct(
        Context $context,
        Registry $registry,
        \Signifyd\Connect\Helper\ConfigHelper $configHelper,
        InvoiceService $invoiceService,
        InvoiceSender $invoiceSender,
        ObjectManagerInterface $objectManager,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        Logger $logger
    )
    {
        $this->configHelper = $configHelper;
        $this->invoiceService = $invoiceService;
        $this->invoiceSender = $invoiceSender;
        $this->objectManager = $objectManager;
        $this->orderFactory = $orderFactory;
        $this->logger = $logger;

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

    public function getOrder()
    {
        if (isset($this->order) == false) {
            $incrementId = $this->getOrderIncrement();

            if (empty($incrementId) == false) {
                $this->order = $this->orderFactory->create()->loadByIncrementId($incrementId);
            }
        }

        return $this->order;
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

        $orderAction = array("action" => null, "reason" => '');
        if (isset($request->score) && $case->getScore() != $request->score) {
            $case->setScore($request->score);
            $order->setSignifydScore($request->score);
        }

        if (isset($request->status) && $case->getSignifydStatus() != $request->status) {
            $case->setSignifydStatus($request->status);
        }

        if (isset($request->guaranteeDisposition) && $case->getGuarantee() != $request->guaranteeDisposition) {
            $case->setGuarantee($request->guaranteeDisposition);
            $order->setSignifydGuarantee($request->guaranteeDisposition);
            $orderAction = $this->handleGuaranteeChange($caseData) ?: $orderAction;
        }

        $case->setCode($request->caseId);
        $order->setSignifydCode($request->caseId);

        $guarantee = $case->getGuarantee();
        $score = $case->getScore();
        if (empty($guarantee) == false && $guarantee != 'N/A' && empty($score) == false) {
            $case->setMagentoStatus(CaseRetry::PROCESSING_RESPONSE_STATUS);
            $case->setUpdated(strftime('%Y-%m-%d %H:%M:%S', time()));
        }

        if (isset($request->testInvestigation)) {
            $case->setEntries('testInvestigation', $request->testInvestigation);
        }

        try{
            $order->getResource()->save($order);
            $this->getResource()->save($case);
            $this->updateOrder($caseData, $orderAction, $case);
            $this->logger->info('Case was saved, id:' . $case->getIncrementId());
        } catch (\Exception $e){
            $this->logger->critical($e->__toString());
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
    public function updateOrder($caseData, $orderAction, $case)
    {
        $this->logger->debug("Update order with action: " . print_r($orderAction, true));

        /** @var $order \Magento\Sales\Model\Order */
        $order = $caseData['order'];
        $completeCase = false;

        $completeOrderStates = array(Order::STATE_CANCELED, Order::STATE_COMPLETE, Order::STATE_CLOSED);

        if (in_array($order->getState(), $completeOrderStates)) {
            $completeCase = true;
        }

        switch ($orderAction["action"]) {
            case "hold":
                if ($order->canHold()) {
                    try {
                        $order->hold();

                        $completeCase = true;

                        $order->addStatusHistoryComment("Signifyd: {$orderAction["reason"]}");
                    } catch (\Exception $e){
                        $this->logger->debug($e->__toString());

                        $orderAction['action'] = false;

                        $order->addStatusHistoryComment("Signifyd: order cannot be updated to on hold,{$e->getMessage()}");
                    }
                } else {
                    $notHoldableStates = [
                        Order::STATE_CANCELED,
                        Order::STATE_PAYMENT_REVIEW,
                        Order::STATE_COMPLETE,
                        Order::STATE_CLOSED,
                        Order::STATE_HOLDED
                    ];

                    if ($order->getState() == Order::STATE_HOLDED) {
                        $completeCase = true;
                    }

                    if (in_array($order->getState(), $notHoldableStates)) {
                        $reason = "order is on {$order->getState()} state";
                    } elseif ($order->getActionFlag(Order::ACTION_FLAG_HOLD) === false) {
                        $reason = "order action flag is set to do not hold";
                    } else {
                        $reason = "unknown reason";
                    }

                    $this->logger->debug("Order {$order->getIncrementId()} can not be held because {$reason}");

                    $orderAction['action'] = false;

                    $order->addStatusHistoryComment("Signifyd: order cannot be updated to on hold, {$reason}");
                }
                break;

            case "unhold":
                if ($order->canUnhold()) {
                    $this->logger->debug('Unhold order action');
                    try{
                        $order->unhold();

                        $completeCase = true;

                        $order->addStatusHistoryComment("Signifyd: order status updated, {$orderAction["reason"]}");
                    } catch (\Exception $e){
                        $this->logger->debug($e->__toString());

                        $orderAction['action'] = false;

                        $order->addStatusHistoryComment("Signifyd: order status cannot be updated, {$e->getMessage()}");
                    }
                } else {
                    if ($order->getState() != Order::STATE_HOLDED && $order->isPaymentReview() == false) {
                        $reason = "order is not holded";
                        $completeCase = true;
                    } elseif ($order->isPaymentReview()) {
                        $reason = 'order is in payment review';
                    } elseif ($order->getActionFlag(Order::ACTION_FLAG_UNHOLD) === false) {
                        $reason = "order action flag is set to do not unhold";
                    } else {
                        $reason = "unknown reason";
                    }

                    $this->logger->debug(
                        "Order {$order->getIncrementId()} ({$order->getState()} > {$order->getStatus()}) " .
                        "can not be removed from hold because {$reason}. " .
                        "Case status: {$case->getSignifydStatus()}"
                    );

                    $orderAction['action'] = false;

                    $order->addStatusHistoryComment("Signifyd: order status cannot be updated, {$reason}");
                }
                break;

            case "cancel":
                if ($order->canUnhold()) {
                    $order = $order->unhold();
                }

                if ($order->canCancel()) {
                    try {
                        $order->cancel();

                        $completeCase = true;

                        $order->addStatusHistoryComment("Signifyd: order canceled, {$orderAction["reason"]}");
                    } catch (\Exception $e) {
                        $this->logger->debug($e->__toString());

                        $orderAction['action'] = false;

                        $order->addStatusHistoryComment("Signifyd: order cannot be canceled, {$e->getMessage()}");
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
                        $reason = "conflict with payment review";
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
                            $reason = "all order items are invoiced";
                            $completeCase = true;
                        } else {
                            $reason = "unknown reason";
                        }
                    }

                    $this->logger->debug("Order {$order->getIncrementId()} cannot be canceled because {$reason}");

                    $orderAction['action'] = false;

                    $order->addStatusHistoryComment("Signifyd: order cannot be canceled, {$reason}");
                }

                if ($orderAction['action'] == false && $order->canHold()) {
                    $order->hold();
                }
                break;

            case "capture":
                try {
                    if ($order->canUnhold()) {
                        $order->unhold();
                    }

                    if ($order->canInvoice()) {
                        /** @var \Magento\Sales\Model\Order\Invoice $invoice */
                        $invoice = $this->invoiceService->prepareInvoice($order);

                        if ($invoice->isEmpty()) {
                            throw new \Exception('failed to generate invoice');
                        }

                        if ($invoice->getTotalQty() == 0) {
                            throw new \Exception('no items found to invoice');
                        }

                        $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE);
                        $invoice->addComment('Signifyd: Automatic invoice');
                        $invoice->register();

                        $order->setCustomerNoteNotify(true);
                        $order->setIsInProcess(true);

                        $transactionSave = $this->objectManager->create(
                            \Magento\Framework\DB\Transaction::class
                        )->addObject(
                            $invoice
                        )->addObject(
                            $order
                        );
                        $transactionSave->save();

                        $order->addStatusHistoryComment("Signifyd: create order invoice: {$invoice->getIncrementId()}");
                        $order->save();

                        // Avoid to save order agains, which trigger Magento's exception
                        $order->setDataChanges(false);

                        $this->logger->debug('Invoice was created for order: ' . $order->getIncrementId());

                        // Send invoice email
                        try {
                            $this->invoiceSender->send($invoice);
                        } catch (\Exception $e) {
                            $this->logger->debug('Failed to send the invoice email: ' . $e->getMessage());
                        }

                        $completeCase = true;
                    } else {
                        $notInvoiceableStates = [
                            Order::STATE_CANCELED,
                            Order::STATE_PAYMENT_REVIEW,
                            Order::STATE_COMPLETE,
                            Order::STATE_CLOSED,
                            Order::STATE_HOLDED
                        ];

                        if (in_array($order->getState(), $notInvoiceableStates)) {
                            $reason = "order is on {$order->getState()} state";
                        } elseif ($order->getActionFlag(self::ACTION_FLAG_INVOICE) === false) {
                            $reason = "order action flag is set to do not invoice";
                        } else {
                            foreach ($this->getAllItems() as $item) {
                                if ($item->getQtyToInvoice() > 0 && !$item->getLockedDoInvoice()) {
                                    return true;
                                }
                            }

                            $canInvoiceAny = false;

                            foreach ($order->getAllItems() as $item) {
                                if ($item->getQtyToInvoice() > 0 && !$item->getLockedDoInvoice()) {
                                    $canInvoiceAny = true;
                                    break;
                                }
                            }

                            if ($canInvoiceAny) {
                                $reason = "unknown reason";
                            } else {
                                $reason = "no items can be invoiced";
                                $completeCase = true;
                            }
                        }

                        $this->logger->debug("Order {$order->getIncrementId()} can not be invoiced because {$reason}");

                        $orderAction['action'] = false;

                        $order->addStatusHistoryComment("Signifyd: unable to create invoice: {$reason}");

                        if ($order->canHold()) {
                            $order->hold();
                        }
                    }
                } catch (\Exception $e) {
                    $this->logger->debug('Exception while creating invoice: ' . $e->__toString());

                    if ($order->canHold()) {
                        $order->hold();
                    }

                    $order->addStatusHistoryComment("Signifyd: unable to create invoice: {$e->getMessage()}");

                    $orderAction['action'] = false;
                }

                break;

            // Nothing is an action from Signifyd workflow, different from when no action is given (null or empty)
            // If workflow is set to do nothing, so complete the case
            case 'nothing':
                $orderAction['action'] = false;

                try {
                    $completeCase = true;
                } catch (\Exception $e) {
                    $this->logger->debug($e->__toString());
                    return false;
                }
                break;
        }

        if ($order->hasDataChanges()) {
            $order->getResource()->save($order);
        }

        if ($completeCase) {
            $case->setMagentoStatus(CaseRetry::COMPLETED_STATUS)
                ->setUpdated(strftime('%Y-%m-%d %H:%M:%S', time()));
            $case->getResource()->save($case);
        }

        return true;
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

        $this->logger->debug("Signifyd: Positive action for {$caseData['case']->getOrderIncrement()}: " . $positiveAction);
        $request = $caseData['request'];
        switch ($request->guaranteeDisposition){
            case "DECLINED":
                return array("action" => $negativeAction, "reason" => "guarantee declined");
                break;
            case "APPROVED":
                return array("action" => $positiveAction, "reason" => "guarantee approved");
                break;
            default:
                $this->logger->debug("Signifyd: Unknown guaranty: " . $request->guaranteeDisposition);
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
            return $this->configHelper->getConfigData('signifyd/advanced/guarantee_positive_action', $this);
        }
    }

    public function getNegativeAction()
    {
        if ($this->isHoldReleased()) {
            return 'nothing';
        } else {
            return $this->configHelper->getConfigData('signifyd/advanced/guarantee_negative_action', $this);
        }
    }

    /**
     * Everytime a update is triggered reset retries
     *
     * @param $updated
     * @return mixed
     */
    public function setUpdated($updated)
    {
        $this->setRetries(0);

        return parent::setUpdated($updated);
    }
}
