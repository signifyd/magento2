<?php

namespace Signifyd\Connect\Model\Api;

use Signifyd\Connect\Model\Registry;
use Magento\Sales\Model\Order;
use Signifyd\Connect\Logger\Logger;

class SaleOrder
{
    /**
     * @var Registry
     */
    public $registry;

    /**
     * @var PurchaseFactory
     */
    public $purchaseFactory;

    /**
     * @var UserAccountFactory
     */
    public $userAccountFactory;

    /**
     * @var CoverageRequestsFactory
     */
    public $coverageRequestsFactory;

    /**
     * @var DeviceFactory
     */
    public $deviceFactory;

    /**
     * @var MerchantPlatformFactory
     */
    public $merchantPlatformFactory;

    /**
     * @var SignifydClientFactory
     */
    public $signifydClientFactory;

    /**
     * @var TransactionsFactory
     */
    public $transactionsFactory;

    /**
     * @var TagsFactory
     */
    public $tagsFactory;

    /**
     * @var SellersFactory
     */
    public $sellersFactory;

    /**
     * @var CustomerOrderRecommendationFactory
     */
    public $customerOrderRecommendationFactory;

    /**
     * @var MembershipsFactory
     */
    public $membershipsFactory;

    /**
     * @var MerchantCategoryCodeFactory
     */
    public $merchantCategoryCodeFactory;

    /**
     * @var Logger
     */
    public $logger;

    /**
     * SaleOrder construct.
     *
     * @param Registry $registry
     * @param PurchaseFactory $purchaseFactory
     * @param UserAccountFactory $userAccountFactory
     * @param CoverageRequestsFactory $coverageRequestsFactory
     * @param DeviceFactory $deviceFactory
     * @param MerchantPlatformFactory $merchantPlatformFactory
     * @param SignifydClientFactory $signifydClientFactory
     * @param TransactionsFactory $transactionsFactory
     * @param TagsFactory $tagsFactory
     * @param SellersFactory $sellersFactory
     * @param CustomerOrderRecommendationFactory $customerOrderRecommendationFactory
     * @param MembershipsFactory $membershipsFactory
     * @param MerchantCategoryCodeFactory $merchantCategoryCodeFactory
     * @param Logger $logger
     */
    public function __construct(
        Registry $registry,
        PurchaseFactory $purchaseFactory,
        UserAccountFactory $userAccountFactory,
        CoverageRequestsFactory $coverageRequestsFactory,
        DeviceFactory $deviceFactory,
        MerchantPlatformFactory $merchantPlatformFactory,
        SignifydClientFactory $signifydClientFactory,
        TransactionsFactory $transactionsFactory,
        TagsFactory $tagsFactory,
        SellersFactory $sellersFactory,
        CustomerOrderRecommendationFactory $customerOrderRecommendationFactory,
        MembershipsFactory $membershipsFactory,
        MerchantCategoryCodeFactory $merchantCategoryCodeFactory,
        Logger $logger
    ) {
        $this->registry = $registry;
        $this->purchaseFactory = $purchaseFactory;
        $this->userAccountFactory = $userAccountFactory;
        $this->coverageRequestsFactory = $coverageRequestsFactory;
        $this->deviceFactory = $deviceFactory;
        $this->merchantPlatformFactory = $merchantPlatformFactory;
        $this->signifydClientFactory = $signifydClientFactory;
        $this->transactionsFactory = $transactionsFactory;
        $this->tagsFactory = $tagsFactory;
        $this->sellersFactory = $sellersFactory;
        $this->customerOrderRecommendationFactory = $customerOrderRecommendationFactory;
        $this->membershipsFactory = $membershipsFactory;
        $this->merchantCategoryCodeFactory = $merchantCategoryCodeFactory;
        $this->logger = $logger;
    }

    /**
     * Construct a new signifyd Order object
     *
     * @param Order $order
     * @return array
     */
    public function __invoke($order)
    {
        $signifydOrder = [];

        try {
            $signifydOrder['orderId'] = $order->getIncrementId();
            $signifydOrder['purchase'] = ($this->purchaseFactory->create())($order);
            $signifydOrder['userAccount'] = ($this->userAccountFactory->create())($order);
            $signifydOrder['memberships'] = ($this->membershipsFactory->create())();
            $signifydOrder['coverageRequests'] = (
                $this->coverageRequestsFactory->create())($order->getPayment()->getMethod()
            );
            $signifydOrder['merchantCategoryCode'] = ($this->merchantCategoryCodeFactory->create())();
            $signifydOrder['device'] = (
                $this->deviceFactory->create()
            )($order->getQuoteId(), $order->getStoreId(), $order);
            $signifydOrder['merchantPlatform'] = ($this->merchantPlatformFactory->create())();
            $signifydOrder['signifydClient'] = ($this->signifydClientFactory->create())();
            $signifydOrder['transactions'] = ($this->transactionsFactory->create())($order);
            $signifydOrder['sellers'] = ($this->sellersFactory->create())();
            $signifydOrder['tags'] = ($this->tagsFactory->create())($order->getStoreId());
            $signifydOrder['customerOrderRecommendation'] = ($this->customerOrderRecommendationFactory->create())();

            /**
             * This registry entry it's used to collect data from some payment methods like Payflow Link
             * It must be unregistered after use
             * @see \Signifyd\Connect\Plugin\Magento\Paypal\Model\Payflowlink
             */
            $this->registry->setData('signifyd_payment_data');
        } catch (\Exception $e) {
            $this->logger->info("Failed to create sale order " . $e->getMessage(), ['entity' => $order]);
        } catch (\Error $e) {
            $this->logger->info("Failed to create sale order " . $e->getMessage(), ['entity' => $order]);
        }

        return $signifydOrder;
    }
}
