<?php

namespace Signifyd\Connect\Model\Api;

use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\ResourceModel\Customer as CustomerResourceModel;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;

class UserAccount
{
    /**
     * @var CustomerFactory
     */
    protected $customerFactory;

    /**
     * @var CustomerResourceModel
     */
    protected $customerResourceModel;

    /**
     * @var OrderCollectionFactory
     */
    protected $orderCollectionFactory;

    /**
     * @param CustomerFactory $customerFactory
     * @param CustomerResourceModel $customerResourceModel
     * @param OrderCollectionFactory $orderCollectionFactory
     */
    public function __construct(
        CustomerFactory $customerFactory,
        CustomerResourceModel $customerResourceModel,
        OrderCollectionFactory $orderCollectionFactory
    ) {
        $this->customerFactory = $customerFactory;
        $this->customerResourceModel = $customerResourceModel;
        $this->orderCollectionFactory = $orderCollectionFactory;
    }

    /**
     * Construct a new UserAccount object
     * @param $entity Order|Quote
     * @return array
     */
    public function __invoke($entity)
    {
        if ($entity instanceof Order) {
            $userAccount = $this->makeUserAccount($entity);
        } elseif ($entity instanceof Quote) {
            $userAccount = $this->makeUserAccountFromQuote($entity);
        } else {
            $userAccount = [];
        }

        return $userAccount;
    }

    /**
     * @param $order Order
     * @return array
     */
    protected function makeUserAccount(Order $order)
    {
        $user = [];
        $user['username'] = $order->getCustomerEmail();
        $user['accountNumber'] = $order->getCustomerId();
        $user['aggregateOrderCount'] = 0;
        $user['aggregateOrderDollars'] = 0.0;
        $user['email'] = $order->getCustomerEmail();
        $user['phone'] = $order->getBillingAddress()->getTelephone();

        /* @var $customer \Magento\Customer\Model\Customer */
        $customer = $this->customerFactory->create();
        $this->customerResourceModel->load($customer, $order->getCustomerId());

        if ($customer !== null && !$customer->isEmpty()) {
            $user['createdDate'] = date('c', strtotime($customer->getCreatedAt()));
            $user['lastUpdateDate'] = date('c', strtotime($customer->getData('updated_at')));
            $user['emailLastUpdateDate'] = null;
            $user['phoneLastUpdateDate'] = null;
            $user['passwordLastUpdateDate'] = null;

            $lastOrders = $this->orderCollectionFactory->create()
                ->addFieldToFilter('customer_id', ['eq' => $customer->getId()])
                ->addFieldToFilter('state', ['nin' => ['closed', 'canceled']])
                ->addFieldToFilter('entity_id', ['neq' => $order->getId()]);

            $lastOrder = $lastOrders->getLastItem();
            $lastOrderId = $lastOrder->getIncrementId();
            $user['lastOrderId'] = isset($lastOrderId) ? $lastOrderId : null;

            /** @var $orders \Magento\Sales\Model\ResourceModel\Order\Collection */
            $orderCollection = $this->orderCollectionFactory->create();
            $orderCollection->addFieldToFilter('customer_id', $order->getCustomerId());
            $orderCollection->load();

            /** @var $orderCollection \Magento\Sales\Model\Order*/
            foreach ($orderCollection as $o) {
                $user['aggregateOrderCount']++;
                $user['aggregateOrderDollars'] += floatval($o->getGrandTotal());
            }
        }

        return $user;
    }

    /**
     * @param $quote Quote
     * @return array
     */
    protected function makeUserAccountFromQuote(Quote $quote)
    {
        $user = [];
        $user['email'] = $quote->getCustomerEmail();
        $user['username'] = $quote->getCustomerEmail();
        $user['accountNumber'] = $quote->getCustomerId();
        $user['phone'] = $quote->getBillingAddress()->getTelephone();
        $user['aggregateOrderCount'] = 0;
        $user['aggregateOrderDollars'] = 0.0;

        /* @var $customer \Magento\Customer\Model\Customer */
        $customer = $this->customerFactory->create();
        $this->customerResourceModel->load($customer, $quote->getCustomerId());

        if ($customer !== null && !$customer->isEmpty()) {
            $user['createdDate'] = date('c', strtotime($customer->getCreatedAt()));
            $user['lastUpdateDate'] = date('c', strtotime($customer->getData('updated_at')));
            $user['emailLastUpdateDate'] = null;
            $user['phoneLastUpdateDate'] = null;
            $user['passwordLastUpdateDate'] = null;

            $lastOrders = $this->orderCollectionFactory->create()
                ->addFieldToFilter('customer_id', ['eq' => $customer->getId()])
                ->addFieldToFilter('state', ['nin' => ['closed', 'canceled']]);

            $lastOrder = $lastOrders->getLastItem();
            $lastOrderId = $lastOrder->getIncrementId();
            $user['lastOrderId'] = isset($lastOrderId) ? $lastOrderId : null;

            /** @var $orders \Magento\Sales\Model\ResourceModel\Order\Collection */
            $orderCollection = $this->orderCollectionFactory->create();
            $orderCollection->addFieldToFilter('customer_id', $quote->getCustomerId());
            $orderCollection->load();

            /** @var $orderCollection \Magento\Sales\Model\Order */
            foreach ($orderCollection as $o) {
                $user['aggregateOrderCount']++;
                $user['aggregateOrderDollars'] += floatval($o->getGrandTotal());
            }
        }

        return $user;
    }
}
