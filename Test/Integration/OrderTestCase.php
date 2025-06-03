<?php

declare(strict_types=1);

namespace Signifyd\Connect\Test\Integration;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Quote\Api\GuestCartManagementInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteIdMask;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Sales\Model\OrderRepository;

class OrderTestCase extends TestCase
{
    /**
     * @var QuoteIdMaskFactory
     */
    public $quoteIdMaskFactory;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->quoteIdMaskFactory = $this->objectManager->get(QuoteIdMaskFactory::class);
        $this->transactionsFactory = $this->objectManager->create(
            \Signifyd\Connect\Model\Api\TransactionsFactory::class
        );
        $this->checkoutOrderFactory = $this->objectManager->create(
            \Signifyd\Connect\Model\Api\CheckoutOrderFactory::class
        );
        $this->saleOrderFactory = $this->objectManager->create(\Signifyd\Connect\Model\Api\SaleOrderFactory::class);
        $this->client = $this->objectManager->create(\Signifyd\Connect\Model\Api\Core\Client::class);
    }

    /**
     * @param \Magento\Quote\Model\Quote $quote
     * @return \Magento\Sales\Model\Order
     */
    public function placeQuote($quote)
    {
        /** @var CheckoutSession $checkoutSession */
        $checkoutSession = $this->objectManager->get(CheckoutSession::class);

        $checkoutSession->start();
        $checkoutSession->resetCheckout();
        $checkoutSession->clearQuote();
        $checkoutSession->destroy();
        $checkoutSession->clearStorage();

        $checkoutSession->setQuoteId($quote->getId());

        /** @var QuoteIdMask $quoteIdMask */
        $quoteIdMask = $this->objectManager->get(QuoteIdMaskFactory::class)->create();
        $quoteIdMask->load($quote->getId(), 'quote_id');
        $cartId = $quoteIdMask->getMaskedId();

        /** @var GuestCartManagementInterface $cartManagement */
        $cartManagement = $this->objectManager->get(GuestCartManagementInterface::class);
        $orderId = $cartManagement->placeOrder($cartId);

        $checkoutSession->resetCheckout();
        $checkoutSession->clearQuote();
        $checkoutSession->destroy();
        $checkoutSession->clearStorage();

        return $this->objectManager->get(OrderRepository::class)->get($orderId);
    }

    /**
     * @param $reservedOrderId
     * @return Quote
     */
    public function getQuote($reservedOrderId, $newReservedOrderId = null, $isRejectGuarantee = false)
    {
        if (empty($newReservedOrderId) == true) {
            $newReservedOrderId = $this->incrementId;
        }

        /** @var Quote $quote */
        $quote = $this->objectManager->create(Quote::class);
        $quote->load($reservedOrderId, 'reserved_order_id');
        $quote->setReservedOrderId($newReservedOrderId);

        if ($isRejectGuarantee) {
            $quote->getShippingAddress()->setEmail('testdeclined@signifyd.com');
            $quote->getBillingAddress()->setEmail('testdeclined@signifyd.com');
        }

        $quote->save();

        return $quote;
    }

    public function refundOrder($mode = 'full', $offline = true)
    {
        $order = $this->getOrder();
        $itemsToCreditMemo = [];
        $itemsToInvoice = [];

        switch ($mode) {
            case 'partial':
                /** @var $item \Magento\Sales\Model\Order\Item */
                foreach ($order->getAllItems() as $item) {
                    $itemsToInvoice[$item->getId()] = $item->getQty();
                }

                /** @var $item \Magento\Sales\Model\Order\Item */
                $item = $order->getAllItems()[0];

                $itemsToCreditMemo['qtys'] = [$item->getId() => 1];
                break;
        }

        /** @var \Magento\Sales\Model\Order\InvoiceFactory $invoiceFactory */
        $invoiceFactory = $this->objectManager->get(\Magento\Sales\Api\InvoiceManagementInterface::class);

        /** @var $invoice \Magento\Sales\Model\Order\Invoice */
        $invoice = $invoiceFactory->prepareInvoice($order, $itemsToInvoice);
        $invoice->register();
        if ($invoice->canCapture()) {
            $invoice->capture();
        }
        $invoice->save();
        $order->save();

        $invoice = $this->objectManager
            ->get(\Magento\Sales\Api\InvoiceRepositoryInterface::class)
            ->get($invoice->getId());

        /** @var \Magento\Sales\Model\Order\CreditmemoFactory $creditmemoFactory */
        $creditmemoFactory = $this->objectManager->get(\Magento\Sales\Model\Order\CreditmemoFactory::class);
        $creditmemo = $creditmemoFactory->createByInvoice($invoice, $itemsToCreditMemo);

        /** @var \Magento\Sales\Api\CreditmemoManagementInterface $creditmemoManagement */
        $creditmemoManagement = $this->objectManager->create(\Magento\Sales\Api\CreditmemoManagementInterface::class);
        $creditmemoManagement->refund($creditmemo, $offline);

        $order->save();
        $creditmemo->save();
    }

    public function processReviewCase($isRejectGuarantee = false)
    {
        $this->placeQuote($this->getQuote('guest_quote', null, $isRejectGuarantee));
        $this->updateCaseForRetry();
        $this->tryToReviewCase();
    }

    public function processPreAuth()
    {
        /** @var \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository */
        $customerRepository = $this->objectManager->create(CustomerRepositoryInterface::class);
        $customer = $customerRepository->getById(1);

        /** @var \Magento\Quote\Model\Quote $quote */
        $quote = $this->getQuote('guest_quote');
        $quote->setCustomerIsGuest(false);
        $quote->assignCustomer($customer);
        $quote->save();

        $checkoutOrder = $this->checkoutOrderFactory->create();
        $caseFromQuote = $checkoutOrder($quote);
        $caseResponse = $this->client->postCaseFromQuoteToSignifyd($caseFromQuote, $quote);

        return [$caseFromQuote, $caseResponse, $quote];
    }
}
