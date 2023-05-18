<?php

namespace Signifyd\Connect\Model\Api;

use Magento\Framework\Registry;
use Magento\Sales\Model\Order;

class SaleOrder
{
    /**
     * @var Registry
     */
    protected $registry;

    /**
     * @param Registry $registry
     */
    public function __construct(
        Registry $registry
    ) {
        $this->registry = $registry;
    }

    /**
     * Construct a new signifyd Order object
     * @param $order Order
     * @return array
     */
    public function __invoke($order)
    {
        $signifydOrder = [];

        $signifydOrder['orderId'] = $order->getIncrementId();
        $signifydOrder['purchase'] = $this->makePurchase($order);
        $signifydOrder['userAccount'] = $this->makeUserAccount($order);
        $signifydOrder['memberships'] = $this->makeMemberships();
        $signifydOrder['coverageRequests'] = $this->getDecisionRequest($order->getPayment()->getMethod());
        $signifydOrder['merchantCategoryCode'] = $this->makeMerchantCategoryCode();
        $signifydOrder['device'] = $this->makeDevice($order->getQuoteId(), $order->getStoreId(), $order);
        $signifydOrder['merchantPlatform'] = $this->getMerchantPlataform();
        $signifydOrder['signifydClient'] = $this->makeVersions();
        $signifydOrder['transactions'] = $this->makeTransactions($order);
        $signifydOrder['sellers'] = $this->getSellers();
        $signifydOrder['tags'] = $this->getTags($order->getStoreId());
        $signifydOrder['customerOrderRecommendation'] = $this->getCustomerOrderRecommendation();

        /**
         * This registry entry it's used to collect data from some payment methods like Payflow Link
         * It must be unregistered after use
         * @see \Signifyd\Connect\Plugin\Magento\Paypal\Model\Payflowlink
         */
        $this->registry->unregister('signifyd_payment_data');

        return $signifydOrder;
    }
}