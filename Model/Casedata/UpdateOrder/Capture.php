<?php
/**
 * Copyright 2015 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */
namespace Signifyd\Connect\Model\Casedata\UpdateOrder;

use Magento\Framework\DB\TransactionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\ResourceModel\Order as OrderResourceModel;
use Magento\Sales\Model\ResourceModel\Order\Invoice as InvoiceResourceModel;
use Magento\Sales\Model\Service\InvoiceService;
use Signifyd\Connect\Helper\ConfigHelper;
use Signifyd\Connect\Helper\OrderHelper;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\Casedata;

/**
 * Defines link data for the comment field in the config page
 */
class Capture
{
    /**
     * @var ConfigHelper
     */
    protected $configHelper;

    /**
     * @var OrderHelper
     */
    protected $orderHelper;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var OrderResourceModel
     */
    protected $orderResourceModel;

    /**
     * @var InvoiceService
     */
    protected $invoiceService;

    /**
     * @var InvoiceSender
     */
    protected $invoiceSender;

    /**
     * @var InvoiceResourceModel
     */
    protected $invoiceResourceModel;

    /**
     * @var TransactionFactory
     */
    protected $transactionFactory;

    /**
     * @param ConfigHelper $configHelper
     * @param OrderHelper $orderHelper
     * @param Logger $logger
     * @param OrderResourceModel $orderResourceModel
     * @param InvoiceService $invoiceService
     * @param InvoiceSender $invoiceSender
     * @param InvoiceResourceModel $invoiceResourceModel
     * @param TransactionFactory $transactionFactory
     */
    public function __construct(
        ConfigHelper $configHelper,
        OrderHelper $orderHelper,
        Logger $logger,
        OrderResourceModel $orderResourceModel,
        InvoiceService $invoiceService,
        InvoiceSender $invoiceSender,
        InvoiceResourceModel $invoiceResourceModel,
        TransactionFactory $transactionFactory
    ) {
        $this->configHelper = $configHelper;
        $this->orderHelper = $orderHelper;
        $this->logger = $logger;
        $this->orderResourceModel = $orderResourceModel;
        $this->invoiceService = $invoiceService;
        $this->invoiceSender = $invoiceSender;
        $this->invoiceResourceModel = $invoiceResourceModel;
        $this->transactionFactory = $transactionFactory;
    }

    public function __invoke($order, $case, $enableTransaction, $completeCase)
    {
        try {
            if ($order->canUnhold()) {
                $order->unhold();
                $this->orderResourceModel->save($order);
            }

            $order = $case->getOrder(true);

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
                $case->setEntries('fail', 1);
                $this->orderHelper->addCommentToStatusHistory(
                    $order,
                    "Signifyd: unable to create invoice: {$reason}"
                );

                $completeCase = $this->validateReason($reason);
                $case->holdOrder($order);
            }
        } catch (\Exception $e) {
            $this->logger->debug('Exception creating invoice: ' . $e->__toString(), ['entity' => $order]);
            $case->setEntries('fail', 1);

            $order = $case->getOrder(true);

            $case->holdOrder($order);

            $this->orderHelper->addCommentToStatusHistory(
                $order,
                "Signifyd: unable to create invoice: {$e->getMessage()}"
            );
        }

        return $completeCase;
    }

    /**
     * @param $invoice
     * @param $order
     * @return void
     */
    protected function sendInvoice($invoice, $order)
    {
        try {
            $this->invoiceSender->send($invoice);
        } catch (\Exception $e) {
            $message = 'Failed to send the invoice email: ' . $e->getMessage();
            $this->logger->debug($message, ['entity' => $order]);
        }
    }

    /**
     * @param $reason
     * @return bool
     */
    protected function validateReason($reason)
    {
        if ($reason == "no items can be invoiced") {
            return true;
        }

        return false;
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
}