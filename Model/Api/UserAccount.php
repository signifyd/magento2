<?php

namespace Signifyd\Connect\Model\Api;

use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\ResourceModel\Customer as CustomerResourceModel;
use Magento\Framework\App\ResourceConnection;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;

class UserAccount
{
    /**
     * @var CustomerFactory
     */
    public $customerFactory;

    /**
     * @var CustomerResourceModel
     */
    public $customerResourceModel;

    /**
     * @var OrderCollectionFactory
     */
    public $orderCollectionFactory;

    /**
     * @var ResourceConnection
     */
    public $resourceConnection;

    /**
     * UserAccount construct.
     *
     * @param CustomerFactory $customerFactory
     * @param CustomerResourceModel $customerResourceModel
     * @param OrderCollectionFactory $orderCollectionFactory
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        CustomerFactory $customerFactory,
        CustomerResourceModel $customerResourceModel,
        OrderCollectionFactory $orderCollectionFactory,
        ResourceConnection $resourceConnection
    ) {
        $this->customerFactory = $customerFactory;
        $this->customerResourceModel = $customerResourceModel;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * Construct a new UserAccount object
     *
     * @param Order|Quote $entity
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
     * Make user account method.
     *
     * @param Order $order
     * @return array
     */
    public function makeUserAccount(Order $order)
    {
        $user = [];
        $user['username'] = $order->getCustomerEmail();
        $user['accountNumber'] = $order->getCustomerId();
        $user['aggregateOrderCount'] = 0;
        $user['aggregateOrderDollars'] = 0.0;
        $user['email'] = $order->getCustomerEmail();
        $user['phone'] = $order->getBillingAddress()->getTelephone();

        /* @var \Magento\Customer\Model\Customer $customer */
        $customer = $this->customerFactory->create();
        $this->customerResourceModel->load($customer, $order->getCustomerId());

        if ($customer !== null && !$customer->isEmpty()) {
            $user['createdDate'] = date('c', strtotime($customer->getCreatedAt()));
            $user['lastUpdateDate'] = date('c', strtotime($customer->getData('updated_at')));
            $user['emailLastUpdateDate'] = null;
            $user['phoneLastUpdateDate'] = null;
            $user['passwordLastUpdateDate'] = null;

            $lastOrders = $this->orderCollectionFactory->create()
                ->addFieldToSelect('increment_id')
                ->addFieldToFilter('customer_id', ['eq' => $customer->getId()])
                ->addFieldToFilter('state', ['nin' => ['closed', 'canceled']])
                ->addFieldToFilter('entity_id', ['neq' => $order->getId()]);

            $lastOrders->setOrder('entity_id', 'desc');
            $lastOrders->getSelect()->limit(1);
            $lastOrderId = $lastOrders->getFirstItem()->getIncrementId();
            $user['lastOrderId'] = isset($lastOrderId) ? $lastOrderId : null;

            $historyData = $this->getAggregateData($order->getCustomerId());

            if (isset($historyData['sum_grand_total']) &&
                isset($historyData['totals_order'])
            ) {
                $user['aggregateOrderCount'] = (int) $historyData['totals_order'];
                $user['aggregateOrderDollars'] = number_format($historyData['sum_grand_total'], 2, '.', '');
            }
        }

        return $user;
    }

    /**
     * Get aggregate data method.
     *
     * @param string|int $customerId
     * @return false|mixed
     */
    public function getAggregateData($customerId)
    {
        $connection = $this->resourceConnection->getConnection();
        $salesOrder = $this->resourceConnection->getTableName('sales_order');

        $select = $connection->select()
            ->from(
                ['so' => $salesOrder],
                [
                    'customer_id',
                    'sum_grand_total' => new \Zend_Db_Expr('SUM(grand_total)'),
                    'totals_order' => new \Zend_Db_Expr('COUNT(*)')
                ]
            )
            ->where('so.customer_id = ?', $customerId);

        $historyDataArray = $connection->fetchRow($select);

        return $historyDataArray ?: false;
    }

    /**
     * Make user account from quote method.
     *
     * @param Quote $quote
     * @return array
     */
    public function makeUserAccountFromQuote(Quote $quote)
    {
        $user = [];
        $user['email'] = $quote->getCustomerEmail();
        $user['username'] = $quote->getCustomerEmail();
        $user['accountNumber'] = $quote->getCustomerId();
        $user['phone'] = $quote->getBillingAddress()->getTelephone();
        $user['aggregateOrderCount'] = 0;
        $user['aggregateOrderDollars'] = 0.0;

        /* @var \Magento\Customer\Model\Customer $customer */
        $customer = $this->customerFactory->create();
        $this->customerResourceModel->load($customer, $quote->getCustomerId());

        if ($customer !== null && !$customer->isEmpty()) {
            $user['createdDate'] = date('c', strtotime($customer->getCreatedAt()));
            $user['lastUpdateDate'] = date('c', strtotime($customer->getData('updated_at')));
            $user['emailLastUpdateDate'] = null;
            $user['phoneLastUpdateDate'] = null;
            $user['passwordLastUpdateDate'] = null;

            $lastOrders = $this->orderCollectionFactory->create()
                ->addFieldToSelect('increment_id')
                ->addFieldToFilter('customer_id', ['eq' => $customer->getId()])
                ->addFieldToFilter('state', ['nin' => ['closed', 'canceled']]);

            $lastOrders->setOrder('entity_id', 'desc');
            $lastOrders->getSelect()->limit(1);
            $lastOrderId = $lastOrders->getFirstItem()->getIncrementId();
            $user['lastOrderId'] = isset($lastOrderId) ? $lastOrderId : null;

            $historyData = $this->getAggregateData($quote->getCustomerId());

            if (isset($historyData['sum_grand_total']) &&
                isset($historyData['totals_order'])
            ) {
                $user['aggregateOrderCount'] = (int) $historyData['totals_order'];
                $user['aggregateOrderDollars'] = number_format($historyData['sum_grand_total'], 2, '.', '');
            }
        }

        return $user;
    }
}
