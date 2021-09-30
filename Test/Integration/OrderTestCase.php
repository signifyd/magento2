<?php

declare(strict_types=1);

namespace Signifyd\Connect\Test\Integration;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Quote\Api\GuestCartManagementInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteIdMask;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderRepository;
use Signifyd\Connect\Model\Casedata;

class OrderTestCase extends TestCase
{
    /**
     * @var QuoteIdMaskFactory
     */
    protected $quoteIdMaskFactory;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->quoteIdMaskFactory = $this->objectManager->get(QuoteIdMaskFactory::class);
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
    public function getQuote($reservedOrderId, $newReservedOrderId = null)
    {
        if (empty($newReservedOrderId) == true) {
            $newReservedOrderId = $this->incrementId;
        }

        /** @var Quote $quote */
        $quote = $this->objectManager->create(Quote::class);
        $quote->load($reservedOrderId, 'reserved_order_id');
        $quote->setReservedOrderId($newReservedOrderId);
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

    public function createCase($code = null, $magentoStatus = Casedata::WAITING_SUBMISSION_STATUS)
    {
        /** @var \Signifyd\Connect\Model\Casedata $case */
        $case = $this->objectManager->create(Casedata::class);
        $order = $this->placeQuote($this->getQuote('guest_quote'));

        $case->setData([
            'order_increment' => $this->incrementId,
            // Case must be created with 60 seconds before now in order to trigger cron on retries
            'created' => strftime('%Y-%m-%d %H:%M:%S', time()-60),
            'updated' => strftime('%Y-%m-%d %H:%M:%S', time()-60),
            'magento_status' => $magentoStatus,
            'code' => $code,
            'order_id' => $order->getId()
        ]);
        $case->save();

        return $case;
    }

    public function createOrderCustomerLoggedIn()
    {
        /** @var \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository */
        $customerRepository = $this->objectManager->create(CustomerRepositoryInterface::class);
        $customer = $customerRepository->getById(1);

        /** @var \Magento\Quote\Model\Quote $quote */
        $quote = $this->getQuote('guest_quote');
        $quote->setCustomerIsGuest(false);
        $quote->assignCustomer($customer);
        $quote->save();

        return $this->placeQuote($quote);
    }

    public function createApprovedCompleteCase($code = null)
    {
        /** @var \Signifyd\Connect\Model\Casedata $case */
        $case = $this->objectManager->create(Casedata::class);
        $order = $this->placeQuote($this->getQuote('guest_quote'));

        $case->setData([
            'order_increment' => $this->incrementId,
            // Case must be created with 60 seconds before now in order to trigger cron on retries
            'created' => strftime('%Y-%m-%d %H:%M:%S', time()-60),
            'updated' => strftime('%Y-%m-%d %H:%M:%S', time()-60),
            'code' => $code,
            'order_id' => $order->getId(),
            'signifyd_status' => "PENDING",
            'origin_store_code' => "default",
            'score' => 999,
            'guarantee' => "ACCEPT",
            'magento_status' => "completed",
            'policy_name' => "post_auth"
        ]);
        $case->save();

        return $case;
    }

    public function createDeclinedCompleteCase($code = null)
    {
        /** @var \Signifyd\Connect\Model\Casedata $case */
        $case = $this->objectManager->create(Casedata::class);
        $order = $this->placeQuote($this->getQuote('guest_quote'));
        $order->setStatus(Order::STATE_CANCELED);
        $order->setState(Order::STATE_CANCELED);
        $order->save();

        $case->setData([
            'order_increment' => $this->incrementId,
            // Case must be created with 60 seconds before now in order to trigger cron on retries
            'created' => strftime('%Y-%m-%d %H:%M:%S', time()-60),
            'updated' => strftime('%Y-%m-%d %H:%M:%S', time()-60),
            'code' => $code,
            'order_id' => $order->getId(),
            'signifyd_status' => "PENDING",
            'origin_store_code' => "default",
            'score' => 333,
            'guarantee' => "DECLINED",
            'magento_status' => "completed",
            'policy_name' => "post_auth"
        ]);
        $case->save();

        return $case;
    }

    public function createShipment(\Magento\Sales\Model\Order $order)
    {
        if (!$order->canShip()) {
            return false;
        }

        /** @var \Magento\Sales\Model\Convert\Order $convertOrder */
        $convertOrder = $this->objectManager->create('Magento\Sales\Model\Convert\Order');
        /** @var \Magento\Sales\Model\Order\Shipment $shipment */
        $shipment = $convertOrder->toShipment($order);

        foreach ($order->getAllItems() AS $orderItem) {
            if (! $orderItem->getQtyToShip() || $orderItem->getIsVirtual()) {
                continue;
            }

            $qtyShipped = $orderItem->getQtyToShip();
            $shipmentItem = $convertOrder->itemToShipmentItem($orderItem)->setQty($qtyShipped);
            $shipment->addItem($shipmentItem);
        }

        $dataTrack = array(
            'carrier_code' => 'ups',
            'title' => 'United Parcel Service',
            'number' => 'TORD23254WERZXd3',
        );

        /** @var \Magento\Sales\Model\Order\Shipment\Track $track */
        $track = $this->objectManager->create('Magento\Sales\Model\Order\Shipment\Track');
        $track->addData($dataTrack);
        $shipment->addTrack($track);

        $shipment->register();
        $shipment->getOrder()->setIsInProcess(true);

        $shipment->save();
        $shipment->getOrder()->save();

        return true;
    }
}
