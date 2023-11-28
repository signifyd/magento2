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
    protected $dateTimeFactory;

    /**
     * @var ProductFactory
     */
    protected $productFactory;

    /**
     * @var ShipmentsFactory
     */
    protected $shipmentsFactory;

    /**
     * @var ReceivedByFactory
     */
    protected $receivedByFactory;

    /**
     * @param DateTimeFactory $dateTimeFactory
     * @param ProductFactory $productFactory
     * @param ShipmentsFactory $shipmentsFactory
     * @param ReceivedByFactory $receivedByFactory
     */
    public function __construct(
        DateTimeFactory $dateTimeFactory,
        ProductFactory $productFactory,
        ShipmentsFactory $shipmentsFactory,
        ReceivedByFactory $receivedByFactory
    ) {
        $this->dateTimeFactory = $dateTimeFactory;
        $this->productFactory = $productFactory;
        $this->shipmentsFactory = $shipmentsFactory;
        $this->receivedByFactory = $receivedByFactory;
    }

    /**
     * Construct a new Purchase object
     * @param $entity Order|Quote
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
     * @param $order Order
     * @return array
     */
    protected function makePurchaseFromOrder(Order $order)
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
                $makeProduct = $this->productFactory->create();
                $purchase['products'][] = $makeProduct($item);
            }
        }

        $makeShipments = $this->shipmentsFactory->create();
        $purchase['shipments'] = $makeShipments($order);
        $purchase['confirmationPhone'] = $order->getBillingAddress()->getTelephone();
        $purchase['totalShippingCost'] = $order->getShippingAmount();
        $couponCode = $order->getCouponCode();
        $receivedBy = $this->receivedByFactory->create();

        if (empty($couponCode) === false) {
            $purchase['discountCodes'] = [
                'amount' => abs($order->getDiscountAmount()),
                'code' => $couponCode
            ];
        }

        $purchase['receivedBy'] = $receivedBy();

        return $purchase;
    }


    /**
     * @param Quote $quote
     * @return array
     */
    protected function makePurchaseFromQuote(Quote $quote)
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
                $makeProduct = $this->productFactory->create();
                $purchase['products'][] = $makeProduct($item);
            }
        }

        $makeShipments = $this->shipmentsFactory->create();
        $shippingAmount = $quote->getShippingAddress()->getShippingAmount();
        $receivedBy = $this->receivedByFactory->create();
        $purchase['shipments'] = $makeShipments($quote);
        $purchase['confirmationPhone'] = $quote->getBillingAddress()->getTelephone();
        $purchase['totalShippingCost'] = is_numeric($shippingAmount) ? floatval($shippingAmount) : null;
        $purchase['discountCodes'] = null;
        $purchase['receivedBy'] = $receivedBy();

        return $purchase;
    }
}
