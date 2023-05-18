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
     * @param DateTimeFactory $dateTimeFactory
     */
    public function __construct(
        DateTimeFactory $dateTimeFactory
    ) {
        $this->dateTimeFactory = $dateTimeFactory;
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
                $purchase['products'][] = $this->makeProduct($item);
            }
        }

        $purchase['shipments'] = $this->makeShipments($order);
        $purchase['confirmationPhone'] = $order->getBillingAddress()->getTelephone();
        $purchase['totalShippingCost'] = $order->getShippingAmount();
        $couponCode = $order->getCouponCode();

        if (empty($couponCode) === false) {
            $purchase['discountCodes'] = [
                'amount' => abs($order->getDiscountAmount()),
                'code' => $couponCode
            ];
        }

        $purchase['receivedBy'] = $this->getReceivedBy();

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
                $purchase['products'][] = $this->makeProductFromQuote($item);
            }
        }

        $shippingAmount = $quote->getShippingAddress()->getShippingAmount();
        $purchase['shipments'] = $this->makeShipmentsFromQuote($quote);
        $purchase['confirmationPhone'] = $quote->getBillingAddress()->getTelephone();
        $purchase['totalShippingCost'] = is_numeric($shippingAmount) ? floatval($shippingAmount) : null;
        $purchase['discountCodes'] = null;
        $purchase['receivedBy'] = $this->getReceivedBy();

        return $purchase;
    }
}