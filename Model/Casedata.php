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
use Magento\Framework\Serialize\SerializerInterface;
use Signifyd\Connect\Helper\OrderHelper;

/**
 * ORM model declaration for case data
 */
class Casedata extends AbstractModel
{
    /* The status when a case is created */
    const WAITING_SUBMISSION_STATUS     = "waiting_submission";

    /* The status for a case when the first response from Signifyd is received */
    const IN_REVIEW_STATUS              = "in_review";

    /* The status for a case when the case is processing the response */
    const PROCESSING_RESPONSE_STATUS    = "processing_response";

    /* The status for a case that is completed */
    const COMPLETED_STATUS              = "completed";

    /* The status for a case that is awiting async payment methods to finish */
    const ASYNC_WAIT                    = "async_wait";

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
     * @var SerializerInterface
     */
    protected $serializer;

    /**
     * @var OrderHelper
     */
    protected $orderHelper;

    /**
     * Casedata constructor.
     * @param Context $context
     * @param Registry $registry
     * @param \Signifyd\Connect\Helper\ConfigHelper $configHelper
     * @param InvoiceService $invoiceService
     * @param Logger
     * @param SerializerInterface $serializer
     * @param OrderHelper $orderHelper
     */
    public function __construct(
        Context $context,
        Registry $registry,
        \Signifyd\Connect\Helper\ConfigHelper $configHelper,
        InvoiceService $invoiceService,
        InvoiceSender $invoiceSender,
        ObjectManagerInterface $objectManager,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        Logger $logger,
        SerializerInterface $serializer,
        OrderHelper $orderHelper
    ) {
        $this->configHelper = $configHelper;
        $this->invoiceService = $invoiceService;
        $this->invoiceSender = $invoiceSender;
        $this->objectManager = $objectManager;
        $this->orderFactory = $orderFactory;
        $this->logger = $logger;
        $this->serializer = $serializer;
        $this->orderHelper = $orderHelper;

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
        $this->_init(\Signifyd\Connect\Model\ResourceModel\Casedata::class);
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
        try {
            /** @var $case \Signifyd\Connect\Model\Casedata */
            $case = $caseData['case'];
            $response = $caseData['response'];
            $order = $caseData['order'];
            $orderAction = ["action" => null, "reason" => ''];

            if (isset($response->score) && $case->getScore() != $response->score) {
                $case->setScore(floor($response->score));
                $order->setSignifydScore($response->score);
            }

            if (isset($response->status) && $case->getSignifydStatus() != $response->status) {
                $case->setSignifydStatus($response->status);
            }

            if (isset($response->guaranteeDisposition) && $case->getGuarantee() != $response->guaranteeDisposition) {
                $case->setGuarantee($response->guaranteeDisposition);
                $order->setSignifydGuarantee($response->guaranteeDisposition);
                $orderAction = $this->handleGuaranteeChange($caseData) ?: $orderAction;
            }

            if (isset($response->caseId) && empty($response->caseId) == false) {
                $case->setCode($response->caseId);
                $order->setSignifydCode($response->caseId);
            }

            $guarantee = $case->getGuarantee();
            $score = $case->getScore();

            // If guarentee is PENDING, do not move Magento status to the next step
            if (!empty($guarantee) && $guarantee != 'N/A' && $guarantee != 'PENDING' && !empty($score)) {
                $case->setMagentoStatus(Casedata::PROCESSING_RESPONSE_STATUS);
                $case->setUpdated(strftime('%Y-%m-%d %H:%M:%S', time()));
            }

            if (isset($response->testInvestigation)) {
                $case->setEntries('testInvestigation', $response->testInvestigation);
            }

            $order->getResource()->save($order);
            $this->getResource()->save($case);
            $this->updateOrder($caseData, $orderAction, $case);
            $this->logger->info('Case was saved, id:' . $case->getIncrementId(), ['entity' => $case]);
        } catch (\Exception $e) {
            $this->logger->critical($e->__toString(), ['entity' => $case]);
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
        // phpcs:ignore
        $message = "Update order with action: " . $this->serializer->serialize($orderAction);
        $this->logger->debug($message, ['entity' => $case]);

        /** @var $order \Magento\Sales\Model\Order */
        $order = $caseData['order'];
        $completeCase = false;

        $completeOrderStates = [Order::STATE_CANCELED, Order::STATE_COMPLETE, Order::STATE_CLOSED];

        if (in_array($order->getState(), $completeOrderStates)) {
            $completeCase = true;
        }

        switch ($orderAction["action"]) {
            case "hold":
                if ($order->canHold()) {
                    try {
                        $order->hold();

                        $completeCase = true;

                        $order->addCommentToStatusHistory("Signifyd: {$orderAction["reason"]}");
                    } catch (\Exception $e) {
                        $this->logger->debug($e->__toString(), ['entity' => $case]);

                        $orderAction['action'] = false;

                        $message = "Signifyd: order cannot be updated to on hold, {$e->getMessage()}";
                        $order->addCommentToStatusHistory($message);
                    }
                } else {
                    $reason = $this->orderHelper->getCannotHoldReason($order);
                    $message = "Order {$order->getIncrementId()} can not be held because {$reason}";
                    $this->logger->debug($message, ['entity' => $case]);
                    $orderAction['action'] = false;
                    $order->addStatusHistoryComment("Signifyd: order cannot be updated to on hold, {$reason}");
                }
                break;

            case "unhold":
                if ($order->canUnhold()) {
                    $this->logger->debug('Unhold order action', ['entity' => $case]);
                    try {
                        $order->unhold();

                        $completeCase = true;

                        $order->addStatusHistoryComment("Signifyd: order status updated, {$orderAction["reason"]}");
                    } catch (\Exception $e) {
                        $this->logger->debug($e->__toString(), ['entity' => $case]);

                        $orderAction['action'] = false;

                        $order->addStatusHistoryComment("Signifyd: order status cannot be updated, {$e->getMessage()}");
                    }
                } else {
                    $reason = $this->orderHelper->getCannotUnholdReason($order);

                    $message = "Order {$order->getIncrementId()} ({$order->getState()} > {$order->getStatus()}) " .
                        "can not be removed from hold because {$reason}. " .
                        "Case status: {$case->getSignifydStatus()}";
                    $this->logger->debug($message, ['entity' => $case]);

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
                        $this->logger->debug($e->__toString(), ['entity' => $case]);

                        $orderAction['action'] = false;

                        $order->addStatusHistoryComment("Signifyd: order cannot be canceled, {$e->getMessage()}");
                    }
                } else {
                    $reason = $this->orderHelper->getCannotCancelReason($order);
                    $message = "Order {$order->getIncrementId()} cannot be canceled because {$reason}";
                    $this->logger->debug($message, ['entity' => $case]);
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

                        $this->orderHelper->isInvoiceValid($invoice);

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

                        $message = 'Invoice was created for order: ' . $order->getIncrementId();
                        $this->logger->debug($message, ['entity' => $case]);

                        // Send invoice email
                        try {
                            $this->invoiceSender->send($invoice);
                        } catch (\Exception $e) {
                            $message = 'Failed to send the invoice email: ' . $e->getMessage();
                            $this->logger->debug($message, ['entity' => $case]);
                        }

                        $completeCase = true;
                    } else {
                        $reason = $this->orderHelper->getCannotInvoiceReason($order);
                        $message = "Order {$order->getIncrementId()} can not be invoiced because {$reason}";
                        $this->logger->debug($message, ['entity' => $case]);
                        $orderAction['action'] = false;
                        $order->addStatusHistoryComment("Signifyd: unable to create invoice: {$reason}");

                        if ($order->canHold()) {
                            $order->hold();
                        }
                    }
                } catch (\Exception $e) {
                    $this->logger->debug('Exception while creating invoice: ' . $e->__toString(), ['entity' => $case]);

                    if ($order->canHold()) {
                        $order->hold();
                    }

                    $order->addStatusHistoryComment("Signifyd: unable to create invoice: {$e->getMessage()}");

                    $orderAction['action'] = false;
                }

                break;

            // Do nothing, but do not complete the case on Magento side
            // This action should be used when something is processing on Signifyd end and extension should wait
            // E.g.: Signifyd returns guarantee disposition PENDING because case it is on manual review
            case 'wait':
                $orderAction['action'] = false;
                break;

            // Nothing is an action from Signifyd workflow, different from when no action is given (null or empty)
            // If workflow is set to do nothing, so complete the case
            case 'nothing':
                $orderAction['action'] = false;
                $completeCase = true;
                break;
        }

        if ($order->hasDataChanges()) {
            $order->getResource()->save($order);
        }

        if ($completeCase) {
            $case->setMagentoStatus(Casedata::COMPLETED_STATUS)
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

        $message = "Signifyd: Positive action for {$caseData['case']->getOrderIncrement()}: " . $positiveAction;
        $this->logger->debug($message, ['entity' => $caseData['case']]);
        $response = $caseData['response'];
        switch ($response->guaranteeDisposition) {
            case "DECLINED":
                return ["action" => $negativeAction, "reason" => "guarantee declined"];

            case "APPROVED":
                return ["action" => $positiveAction, "reason" => "guarantee approved"];

            case 'PENDING':
                return ["action" => 'wait', "reason" => 'case in manual review'];

            default:
                $message = "Signifyd: Unknown guaranty: " . $response->guaranteeDisposition;
                $this->logger->debug($message, ['entity' => $caseData['case']]);
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
            try {
                $entries = $this->serializer->unserialize($entries);
            } catch (\InvalidArgumentException $e) {
                $entries = [];
            }
        }

        if (!is_array($entries)) {
            $entries = [];
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

        $entries = $this->serializer->serialize($entries);
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
