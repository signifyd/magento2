<?php
/**
 * Copyright 2015 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Signifyd\Connect\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order\CreditmemoFactory;
use Magento\Sales\Model\Service\CreditmemoService;
use Signifyd\Connect\Helper\ConfigHelper;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\ResourceModel\Order as OrderResourceModel;
use Signifyd\Connect\Logger\Logger;
use Magento\Framework\Serialize\SerializerInterface;
use Signifyd\Connect\Helper\OrderHelper;
use Magento\Sales\Model\ResourceModel\Order\Invoice as InvoiceResourceModel;
use Signifyd\Connect\Model\ResourceModel\Order as SignifydOrderResourceModel;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DB\TransactionFactory;

/**
 * ORM model declaration for case data
 */
class Casedata extends AbstractModel
{
    /* The status when a case is created */
    const WAITING_SUBMISSION_STATUS = "waiting_submission";

    /* The status for a case when the first response from Signifyd is received */
    const IN_REVIEW_STATUS = "in_review";

    /* The status for a case that is completed */
    const COMPLETED_STATUS = "completed";

    /* The status for a case that is awiting async payment methods to finish */
    const ASYNC_WAIT = "async_wait";

    /* The status for new case */
    const NEW = "new";

    /* Synchronous response */
    const PRE_AUTH = "pre_auth";

    /* Asynchronous response */
    const POST_AUTH = "post_auth";

    /**
     * @var ConfigHelper
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
     * @var OrderFactory
     */
    protected $orderFactory;

    /**
     * @var OrderResourceModel
     */
    protected $orderResourceModel;

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
     * @var InvoiceResourceModel
     */
    protected $invoiceResourceModel;

    /**
     * @var CreditmemoFactory
     */
    protected $creditmemoFactory;

    /**
     * @var CreditmemoService
     */
    protected $creditmemoService;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfigInterface;

    /**
     * @var SignifydOrderResourceModel
     */
    protected $signifydOrderResourceModel;

    /**
     * @var TransactionFactory
     */
    protected $transactionFactory;

    /**
     * Casedata constructor.
     * @param Context $context
     * @param Registry $registry
     * @param ConfigHelper $configHelper
     * @param InvoiceService $invoiceService
     * @param InvoiceSender $invoiceSender
     * @param ObjectManagerInterface $objectManager
     * @param OrderFactory $orderFactory
     * @param OrderResourceModel $orderResourceModel
     * @param Logger $logger
     * @param SerializerInterface $serializer
     * @param OrderHelper $orderHelper
     * @param InvoiceResourceModel $invoiceResourceModel
     * @param CreditmemoFactory $creditmemoFactory
     * @param CreditmemoService $creditmemoService
     * @param ScopeConfigInterface $scopeConfigInterface
     * @param SignifydOrderResourceModel $signifydOrderResourceModel
     * @param TransactionFactory $transactionFactory
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ConfigHelper $configHelper,
        InvoiceService $invoiceService,
        InvoiceSender $invoiceSender,
        OrderFactory $orderFactory,
        OrderResourceModel $orderResourceModel,
        Logger $logger,
        SerializerInterface $serializer,
        OrderHelper $orderHelper,
        InvoiceResourceModel $invoiceResourceModel,
        CreditmemoFactory $creditmemoFactory,
        CreditmemoService $creditmemoService,
        ScopeConfigInterface $scopeConfigInterface,
        SignifydOrderResourceModel $signifydOrderResourceModel,
        TransactionFactory $transactionFactory
    ) {
        $this->configHelper = $configHelper;
        $this->invoiceService = $invoiceService;
        $this->invoiceSender = $invoiceSender;
        $this->orderFactory = $orderFactory;
        $this->orderResourceModel = $orderResourceModel;
        $this->logger = $logger;
        $this->serializer = $serializer;
        $this->orderHelper = $orderHelper;
        $this->invoiceResourceModel = $invoiceResourceModel;
        $this->creditmemoFactory = $creditmemoFactory;
        $this->creditmemoService = $creditmemoService;
        $this->scopeConfigInterface = $scopeConfigInterface;
        $this->signifydOrderResourceModel = $signifydOrderResourceModel;
        $this->transactionFactory = $transactionFactory;

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

    public function getOrder($forceLoad = false, $loadForUpdate = false)
    {
        if (isset($this->order) === false || $forceLoad) {
            $orderId = $this->getData('order_id');

            if (empty($orderId) == false) {
                $this->order = $this->orderFactory->create();

                if ($loadForUpdate === true) {
                    $this->signifydOrderResourceModel->loadForUpdate($this->order, $orderId);
                } else {
                    $this->orderResourceModel->load($this->order, $orderId);
                }
            }
        }

        return $this->order;
    }

    /**
     * @param $caseData
     * @return bool
     */
    public function updateCase($response)
    {
        try {
            if (isset($response->score) && $this->getScore() !== $response->score) {
                $this->setScore(floor($response->score));
            }

            $isScoreOnly = $this->configHelper->isScoreOnly();
            $caseScore = $this->getScore();

            if (isset($caseScore) && $isScoreOnly) {
                $this->setMagentoStatus(Casedata::COMPLETED_STATUS);
            }

            if (isset($response->status) && $this->getSignifydStatus() != $response->status) {
                $this->setSignifydStatus($response->status);
            }

            if (isset($response->guaranteeDisposition) && $this->getGuarantee() != $response->guaranteeDisposition) {
                $this->setGuarantee($response->guaranteeDisposition);
            }

            if (isset($response->checkpointAction) && $this->getGuarantee() != $response->checkpointAction) {
                $this->setGuarantee($response->checkpointAction);
            }

            if (isset($response->checkpointActionReason) &&
                $this->getCheckpointActionReason() != $response->checkpointActionReason) {
                $this->setCheckpointActionReason($response->checkpointActionReason);
            }

            if (isset($response->caseId) && empty($response->caseId) == false) {
                $this->setCode($response->caseId);
            }

            if (isset($response->testInvestigation)) {
                $this->setEntries('testInvestigation', $response->testInvestigation);
            }

            $failEntry = $this->getEntries('fail');

            if (isset($failEntry)) {
                $this->unsetEntries('fail');
            }

            $origGuarantee = $this->getOrigData('guarantee');
            $newGuarantee = $this->getData('guarantee');
            $origScore = (int) $this->getOrigData('score');
            $newScore = (int) $this->getData('score');

            if (empty($origGuarantee) == false && $origGuarantee != 'N/A' && $origGuarantee != $newGuarantee ||
                $origScore > 0 && $origScore != $newScore) {
                $message = "Signifyd: case reviewed " .
                    "from {$origGuarantee} ({$origScore}) " .
                    "to {$newGuarantee} ({$newScore})";
                $this->orderHelper->addCommentToStatusHistory($this->getOrder(), $message);
            }
        } catch (\Exception $e) {
            $this->logger->critical($e->__toString(), ['entity' => $this]);
            return false;
        }

        return true;
    }

    /**
     * @param $orderAction
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function updateOrder()
    {
        $orderAction = $this->handleGuaranteeChange();

        $this->logger->debug(
            $message = "Updating order with action: " . $this->serializer->serialize($orderAction),
            ['entity' => $this]
        );

        $enableTransaction = $this->scopeConfigInterface->isSetFlag('signifyd/general/enable_transaction');
        $loadForUpdate = false;

        if ($enableTransaction) {
            $this->logger->info("Begin database transaction");
            $this->orderResourceModel->getConnection()->beginTransaction();
            $loadForUpdate = true;
        }

        try {
            $order = $this->getOrder(true, $loadForUpdate);
            $completeCase = false;

            if (in_array($order->getState(), [Order::STATE_CANCELED, Order::STATE_COMPLETE, Order::STATE_CLOSED])) {
                $orderAction["action"] = 'nothing';
            }

            // When Async e-mail sending it is enabled, do not process the order until the e-mail is sent
            $isAsyncEmailEnabled = $this->configHelper->getConfigData(
                'sales_email/general/async_sending',
                $order,
                true
            );

            if ($isAsyncEmailEnabled && $order->getData('send_email') == 1 && empty($order->getEmailSent())) {
                $this->setEntries('fail', 1);
                $orderAction['action'] = false;

                $message = "Will not process order {$order->getIncrementId()} because async e-mail has not been sent";
                $this->logger->debug($message);
            }

            $storeId = $order->getStoreId();

            $enabledConfig = $this->scopeConfigInterface->getValue(
                'signifyd/general/enabled',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
                $storeId
            );

            $isPassive = $enabledConfig == 'passive';

            if ($isPassive && $orderAction['action'] !== false) {
                $this->orderHelper->addCommentToStatusHistory(
                    $order,
                    "Signifyd: order action {$orderAction['action']}",
                    false,
                    $isPassive
                );

                $orderAction['action'] = false;
                $completeCase = true;
            }

            switch ($orderAction["action"]) {
                case "hold":
                    if ($order->canHold()) {
                        try {
                            $order->hold();
                            $this->orderResourceModel->save($order);

                            $completeCase = true;
                            $this->orderHelper->addCommentToStatusHistory(
                                $order,
                                "Signifyd: {$orderAction["reason"]}"
                            );
                        } catch (\Exception $e) {
                            $this->logger->debug($e->__toString(), ['entity' => $order]);
                            $this->setEntries('fail', 1);

                            $orderAction['action'] = false;

                            $message = "Signifyd: order cannot be updated to on hold, {$e->getMessage()}";
                            $this->orderHelper->addCommentToStatusHistory($order, $message);
                            throw new LocalizedException(__($e->getMessage()));
                        }
                    } else {
                        $reason = $this->orderHelper->getCannotHoldReason($order);
                        $message = "Order {$order->getIncrementId()} can not be held because {$reason}";
                        $this->logger->debug($message, ['entity' => $order]);
                        $this->setEntries('fail', 1);
                        $orderAction['action'] = false;
                        $this->orderHelper->addCommentToStatusHistory(
                            $order,
                            "Signifyd: order cannot be updated to on hold, {$reason}"
                        );

                        if ($order->getState() == Order::STATE_HOLDED) {
                            $completeCase = true;
                        }
                    }
                    break;

                case "unhold":
                    if ($order->canUnhold()) {
                        $this->logger->debug('Unhold order action', ['entity' => $order]);

                        try {
                            $order->unhold();
                            $this->orderResourceModel->save($order);

                            $completeCase = true;

                            $this->orderHelper->addCommentToStatusHistory(
                                $order,
                                "Signifyd: order status updated, {$orderAction["reason"]}"
                            );
                        } catch (\Exception $e) {
                            $this->logger->debug($e->__toString(), ['entity' => $order]);
                            $this->setEntries('fail', 1);

                            $orderAction['action'] = false;

                            $this->orderHelper->addCommentToStatusHistory(
                                $order,
                                "Signifyd: order status cannot be updated, {$e->getMessage()}"
                            );
                            throw new LocalizedException(__($e->getMessage()));
                        }
                    } else {
                        $reason = $this->orderHelper->getCannotUnholdReason($order);

                        $message = "Order {$order->getIncrementId()} ({$order->getState()} > {$order->getStatus()}) " .
                            "can not be removed from hold because {$reason}. " .
                            "Case status: {$this->getSignifydStatus()}";
                        $this->logger->debug($message, ['entity' => $order]);
                        $this->setEntries('fail', 1);

                        $this->orderHelper->addCommentToStatusHistory(
                            $order,
                            "Signifyd: order status cannot be updated, {$reason}"
                        );
                        $orderAction['action'] = false;

                        if ($reason == "order is not holded") {
                            $completeCase = true;
                        }
                    }
                    break;

                case "cancel":
                    if ($order->canUnhold()) {
                        $order = $order->unhold();
                        $this->orderResourceModel->save($order);
                    }

                    if ($order->canCancel()) {
                        try {
                            $order->cancel();
                            $this->orderResourceModel->save($order);

                            $completeCase = true;

                            $this->orderHelper->addCommentToStatusHistory(
                                $order,
                                "Signifyd: order canceled, {$orderAction["reason"]}"
                            );
                        } catch (\Exception $e) {
                            $this->logger->debug($e->__toString(), ['entity' => $order]);
                            $this->setEntries('fail', 1);

                            $orderAction['action'] = false;

                            $this->orderHelper->addCommentToStatusHistory(
                                $order,
                                "Signifyd: order cannot be canceled, {$e->getMessage()}"
                            );
                            throw new LocalizedException(__($e->getMessage()));
                        }
                    } else {
                        $reason = $this->orderHelper->getCannotCancelReason($order);
                        $message = "Order {$order->getIncrementId()} cannot be canceled because {$reason}";
                        $this->logger->debug($message, ['entity' => $order]);
                        $this->setEntries('fail', 1);
                        $orderAction['action'] = false;
                        $this->orderHelper->addCommentToStatusHistory(
                            $order,
                            "Signifyd: order cannot be canceled, {$reason}"
                        );

                        if ($reason == "all order items are invoiced") {
                            $completeCase = true;
                        }
                    }

                    $order = $this->getOrder(true);

                    if ($orderAction['action'] == false && $order->canHold()) {
                        $order->hold();
                        $this->orderResourceModel->save($order);
                    }

                    break;

                case "capture":
                    try {
                        if ($order->canUnhold()) {
                            $order->unhold();
                            $this->orderResourceModel->save($order);
                        }

                        $order = $this->getOrder(true);

                        if ($order->canInvoice()) {
                            /** @var \Magento\Sales\Model\Order\Invoice $invoice */
                            $invoice = $this->invoiceService->prepareInvoice($order);

                            $this->orderHelper->isInvoiceValid($invoice);

                            $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE);
                            $invoice->addComment('Signifyd: Automatic invoice');
                            $invoice->register();

                            $order->setCustomerNoteNotify(true);
                            $order->setIsInProcess(true);

                            $this->handleTransaction($enableTransaction, $order, $invoice);

                            $this->orderHelper->addCommentToStatusHistory(
                                $order,
                                "Signifyd: create order invoice: {$invoice->getIncrementId()}"
                            );

                            $this->logger->debug(
                                'Invoice was created for order: ' . $order->getIncrementId(),
                                ['entity' => $order]
                            );

                            // Send invoice email
                            $this->sendInvoice($invoice, $order);

                            $completeCase = true;
                        } elseif ($order->getInvoiceCollection()->count() > 0) {
                            $this->logger->info("Invoice already created");
                            $completeCase = true;
                        } else {
                            $reason = $this->orderHelper->getCannotInvoiceReason($order);
                            $message = "Order {$order->getIncrementId()} can not be invoiced because {$reason}";
                            $this->logger->debug($message, ['entity' => $order]);
                            $this->setEntries('fail', 1);
                            $orderAction['action'] = false;
                            $this->orderHelper->addCommentToStatusHistory(
                                $order,
                                "Signifyd: unable to create invoice: {$reason}"
                            );

                            $completeCase = $this->validateReason($reason);
                            $this->holdOrder($order);
                        }
                    } catch (\Exception $e) {
                        $this->logger->debug('Exception creating invoice: ' . $e->__toString(), ['entity' => $order]);
                        $this->setEntries('fail', 1);

                        $order = $this->getOrder(true);

                        $this->holdOrder($order);

                        $this->orderHelper->addCommentToStatusHistory(
                            $order,
                            "Signifyd: unable to create invoice: {$e->getMessage()}"
                        );

                        $orderAction['action'] = false;
                    }
                    break;

                case "refund":
                    try {
                        if ($order->canUnhold()) {
                            $order->unhold();
                            $this->orderResourceModel->save($order);
                        }

                        $order = $this->getOrder(true);

                        $invoices = $order->getInvoiceCollection();

                        if ($invoices->getTotalCount() > 0) {
                            $this->createInvoicesCreditMemo($invoices, $order);
                        } else {
                            $this->holdOrder($order);
                            $message = "Signifyd: tried to refund, but there is no invoice to add credit memo";
                            $this->orderHelper->addCommentToStatusHistory($order, $message);
                            $this->logger->debug(
                                "tried to refund, but there is no invoice to add credit memo",
                                ['entity' => $order]
                            );
                        }

                        $completeCase = true;
                    } catch (\Exception $e) {
                        $order = $this->getOrder(true);
                        $this->setEntries('fail', 1);
                        $this->holdOrder($order);

                        $this->logger->debug(
                            'Exception creating creditmemo: ' . $e->__toString(),
                            ['entity' => $order]
                        );

                        $this->orderHelper->addCommentToStatusHistory(
                            $order,
                            "Signifyd: unable to create creditmemo: {$e->getMessage()}"
                        );
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

            if ($completeCase) {
                $this->setMagentoStatus(Casedata::COMPLETED_STATUS)
                    ->setUpdated();
            }

            if ($enableTransaction) {
                $this->orderResourceModel->getConnection()->commit();
            }

            return true;
        } catch (\Exception $e) {
            $this->logger->debug($e->getMessage());

            if ($enableTransaction) {
                $this->orderResourceModel->getConnection()->rollBack();
            }

            return false;
        }
    }

    /**
     * @param $guaranteeDisposition
     * @return array|string[]
     */
    public function handleGuaranteeChange()
    {
        $requestGuarantee = $this->getOrigData('guarantee');
        $caseGuarantee = $this->getData('guarantee');
        /** @var \Magento\Sales\Model\Order $order */
        $order = $this->getOrder(true);

        // Reviewed Cases
        if (($requestGuarantee == 'ACCEPT' || $requestGuarantee == 'APPROVED') &&
            $requestGuarantee != $caseGuarantee
        ) {
            $guaranteeReviewedAction = $this->configHelper->getGuaranteesReviewedAction();

            switch ($guaranteeReviewedAction) {
                case 'refund':
                    $shipments = $order->getShipmentsCollection()->getData();
                    $invoices = $order->getInvoiceCollection()->getData();

                    if (empty($shipments) && !empty($invoices)) {
                        $result =  ["action" => 'refund', "reason" => 'approved guarantees reviewed to declined'];
                    } else {
                        $result = ["action" => 'nothing', "reason" => 'approved guarantees reviewed to declined'];
                    }
                    break;

                case 'nothing':
                    $result = ["action" => 'nothing', "reason" => 'approved guarantees reviewed to declined'];
                    break;

                case 'hold':
                    $result = ["action" => 'hold', "reason" => 'approved guarantees reviewed to declined'];
                    break;
            }

            return $result;
        } elseif (($requestGuarantee == 'REJECT' || $requestGuarantee == 'DECLINED') &&
            $requestGuarantee != $caseGuarantee &&
            $order->getState() === Order::STATE_CANCELED
        ) {
            return ["action" => 'nothing', "reason" => 'declined guarantees reviewed to approved'];
        }

        switch ($this->getGuarantee()) {
            case "REJECT":
            case "DECLINED":
                $result = ["action" => $this->getNegativeAction(), "reason" => "guarantee declined"];
                break;

            case 'ACCEPT':
            case "APPROVED":
                $result = ["action" => $this->getPositiveAction(), "reason" => "guarantee approved"];
                break;

            case 'PENDING':
                $result = ["action" => 'wait', "reason" => 'case in manual review'];
                break;

            default:
                $result = ["action" => '', "reason" => ''];
        }

        $this->logger->debug("Action for {$this->getOrderIncrement()}: {$result['action']}", ['entity' => $this]);

        return $result;
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

    public function unsetEntries($index)
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
            return $this;
        }

        if (!empty($index)) {
            if (isset($entries[$index])) {
                unset($entries[$index]);
            }
        }

        $entries = empty($entries) ? "" : $this->serializer->serialize($entries);
        $this->setData('entries_text', $entries);

        return $this;
    }

    public function isHoldReleased()
    {
        $holdReleased = $this->getEntries('hold_released');
        return (($holdReleased == 1) ? true : false);
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
    public function setUpdated($updated = null)
    {
        if (empty($updated)) {
            $updated = date('Y-m-d H:i:s', time());
        }

        $this->setRetries(0);

        return parent::setUpdated($updated);
    }

    public function sendInvoice($invoice, $order)
    {
        try {
            $this->invoiceSender->send($invoice);
        } catch (\Exception $e) {
            $message = 'Failed to send the invoice email: ' . $e->getMessage();
            $this->logger->debug($message, ['entity' => $order]);
        }
    }

    public function validateReason($reason)
    {
        if ($reason == "no items can be invoiced") {
            return true;
        }

        return false;
    }

    public function holdOrder($order)
    {
        if ($order->canHold()) {
            $order->hold();
            $this->orderResourceModel->save($order);
        }
    }

    public function handleTransaction($enableTransaction, $order, $invoice)
    {
        if ($enableTransaction) {
            $this->orderResourceModel->save($order);
            $this->invoiceResourceModel->save($invoice);
        } else {
            /** @var \Magento\Framework\DB\Transaction $transactionSave */
            $transactionSave = $this->transactionFactory->create();
            $transactionSave->addObject(
                $invoice
            )->addObject(
                $invoice->getOrder()
            );

            $transactionSave->save();
        }
    }

    public function createInvoicesCreditMemo($invoices, $order)
    {
        foreach ($invoices as $invoice) {
            $creditmemo = $this->creditmemoFactory->createByOrder($order);
            $creditmemo->setInvoice($invoice);
            $this->creditmemoService->refund($creditmemo);
            $this->logger->debug(
                'Credit memo was created for order: ' . $order->getIncrementId(),
                ['entity' => $order]
            );
        }
    }
}
