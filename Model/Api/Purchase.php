<?php

namespace Signifyd\Connect\Model\Api;

use Magento\Framework\Stdlib\DateTime\DateTimeFactory;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;

class Purchase
{
    /**
     * @var DateTimeFactory
     */
    public $dateTimeFactory;

    /**
     * @var ProductFactory
     */
    public $productFactory;

    /**
     * @var ShipmentsFactory
     */
    public $shipmentsFactory;

    /**
     * @var ReceivedByFactory
     */
    public $receivedByFactory;

    /**
     * @var OrderSourceFactory
     */
    public $orderSourceFactory;

    /**
     * Purchase construct.
     *
     * @param DateTimeFactory $dateTimeFactory
     * @param ProductFactory $productFactory
     * @param ShipmentsFactory $shipmentsFactory
     * @param ReceivedByFactory $receivedByFactory
     * @param OrderSourceFactory $orderSourceFactory
     */
    public function __construct(
        DateTimeFactory $dateTimeFactory,
        ProductFactory $productFactory,
        ShipmentsFactory $shipmentsFactory,
        ReceivedByFactory $receivedByFactory,
        OrderSourceFactory $orderSourceFactory
    ) {
        $this->dateTimeFactory = $dateTimeFactory;
        $this->productFactory = $productFactory;
        $this->shipmentsFactory = $shipmentsFactory;
        $this->receivedByFactory = $receivedByFactory;
        $this->orderSourceFactory = $orderSourceFactory;
    }

    /**
     * Construct a new Purchase object
     *
     * @param Order|Quote $entity
     * @return array
     */
    public function __invoke($entity)
    {
        if ($entity instanceof Order) {
            $purchase = $this->makePurchaseFromOrder($entity);
        } elseif ($entity instanceof Quote) {
            $purchase = $this->makePurchaseFromQuote($entity);
        } else {
            $purchase = [];
        }

        return $purchase;
    }

    /**
     * Make purchase from order method.
     *
     * @param Order $order
     * @return array
     */
    public function makePurchaseFromOrder(Order $order)
    {
        $originStoreCode = $order->getData('origin_store_code');
        $items = $order->getAllItems();
        $purchase = [];
        $purchase['createdAt'] = date('c', strtotime($order->getCreatedAt()));

        if ($originStoreCode == 'admin') {
            $purchase['orderChannel'] = "PHONE";
        } else {
            $purchase['orderChannel'] = "WEB";
        }

        $purchase['totalPrice'] = $order->getGrandTotal();
        $purchase['currency'] = $order->getOrderCurrencyCode();
        $purchase['confirmationEmail'] = $order->getCustomerEmail();
        $purchase['products'] = [];

        /** @var \Magento\Sales\Model\Order\Item $item */
        foreach ($items as $item) {
            $children = $item->getChildrenItems();

            if (is_array($children) == false || empty($children)) {
                $purchase['products'][] = ($this->productFactory->create())($item);
            }
        }

        $purchase['shipments'] = ($this->shipmentsFactory->create())($order);
        $purchase['confirmationPhone'] = $order->getBillingAddress()->getTelephone();
        $purchase['totalShippingCost'] = $order->getShippingAmount();
        $purchase['orderSource'] = ($this->orderSourceFactory->create())();
        $couponCode = $order->getCouponCode();

        if (empty($couponCode) === false) {
            $purchase['discountCodes'] = [
                'amount' => abs($order->getDiscountAmount()),
                'code' => $couponCode
            ];
        }

        $purchase['receivedBy'] = ($this->receivedByFactory->create())();

        return $purchase;
    }

    /**
     * Make purchase from quote method.
     *
     * @param Quote $quote
     * @return array
     */
    public function makePurchaseFromQuote(Quote $quote)
    {
        $items = $quote->getAllItems();
        $purchase = [];
        $dateTime = $this->dateTimeFactory->create();
        $caseCreateDate = $dateTime->gmtDate();
        $purchase['createdAt'] = date('c', strtotime($caseCreateDate));
        $purchase['orderChannel'] = "WEB";
        $purchase['totalPrice'] = $quote->getGrandTotal();
        $purchase['currency'] = $quote->getQuoteCurrencyCode();
        $purchase['confirmationEmail'] = $quote->getCustomerEmail();
        $purchase['products'] = [];

        /** @var \Magento\Quote\Model\Quote\Item $item */
        foreach ($items as $item) {
            $children = $item->getChildren();

            if (is_array($children) == false || empty($children)) {
                $purchase['products'][] = ($this->productFactory->create())($item);
            }
        }

        $shippingAmount = $quote->getShippingAddress()->getShippingAmount();
        $purchase['shipments'] = ($this->shipmentsFactory->create())($quote);
        $purchase['confirmationPhone'] = $quote->getBillingAddress()->getTelephone();
        $purchase['totalShippingCost'] = is_numeric($shippingAmount) ? floatval($shippingAmount) : null;
        $purchase['orderSource'] = ($this->orderSourceFactory->create())();
        $purchase['discountCodes'] = null;
        $purchase['receivedBy'] = ($this->receivedByFactory->create())();

        return $purchase;
    }
}
