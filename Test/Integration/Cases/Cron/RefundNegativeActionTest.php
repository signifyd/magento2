<?php

namespace Signifyd\Connect\Test\Integration\Cases\Cron;

use Signifyd\Connect\Model\Casedata;

class RefundNegativeActionTest extends CreateTest
{
    public function testCronCreateCase()
    {
        // Bypassing test
    }

    /**
     * @magentoDataFixture configFixture
     */
    public function testRefundNegativeAction()
    {
        /** @var \Magento\Framework\App\Config\Storage\WriterInterface $writerInterface */
        $writerInterface = $this->objectManager->create(\Magento\Framework\App\Config\Storage\WriterInterface::class);
        $writerInterface->save('signifyd/advanced/guarantee_negative_action', 'refund');

        $this->placeQuote($this->getQuote('guest_quote', null, true));
        $this->updateCaseForRetry();
        $order = $this->getOrder();
        $this->invoiceOrder($order);
        $this->tryToReviewCase();

        $case = $this->getCase();
        $order = $this->getOrder();

        $this->assertEquals(Casedata::COMPLETED_STATUS, $case->getData('magento_status'));
        $this->assertEquals('REJECT', $case->getData('guarantee'));
        $this->assertTrue($order->hasCreditmemos());
    }

    public function invoiceOrder($order)
    {
        /** @var \Magento\Sales\Model\Service\InvoiceService $invoiceService */
        $invoiceService = $this->objectManager->create(\Magento\Sales\Model\Service\InvoiceService::class);
        $invoice = $invoiceService->prepareInvoice($order);

        $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE);
        $invoice->addComment('Signifyd: Automatic invoice');
        $invoice->register();

        $order->setCustomerNoteNotify(true);
        $order->setIsInProcess(true);

        /** @var \Magento\Sales\Model\Order\Invoice $orderResourceModel */
        $orderResourceModel = $this->objectManager->create(\Magento\Sales\Model\ResourceModel\Order::class);
        $orderResourceModel->save($order);

        /** @var \Magento\Sales\Model\ResourceModel\Order\Invoice $invoiceResourceModel */
        $invoiceResourceModel = $this->objectManager->create(\Magento\Sales\Model\ResourceModel\Order\Invoice::class);
        $invoiceResourceModel->save($invoice);
    }
}
