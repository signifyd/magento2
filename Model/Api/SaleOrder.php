<?php

namespace Signifyd\Connect\Model\Api;

use Magento\Framework\Registry;
use Magento\Sales\Model\Order;
use Signifyd\Connect\Logger\Logger;

class SaleOrder
{
    /**
     * @var Registry
     */
    protected $registry;

    /**
     * @var PurchaseFactory
     */
    protected $purchaseFactory;

    /**
     * @var UserAccountFactory
     */
    protected $userAccountFactory;

    /**
     * @var CoverageRequestsFactory
     */
    protected $coverageRequestsFactory;

    /**
     * @var DeviceFactory
     */
    protected $deviceFactory;

    /**
     * @var MerchantPlatformFactory
     */
    protected $merchantPlatformFactory;

    /**
     * @var SignifydClientFactory
     */
    protected $signifydClientFactory;

    /**
     * @var TransactionsFactory
     */
    protected $transactionsFactory;

    /**
     * @var TagsFactory
     */
    protected $tagsFactory;

    /**
     * @var SellersFactory
     */
    protected $sellersFactory;

    /**
     * @var CustomerOrderRecommendationFactory
     */
    protected $customerOrderRecommendationFactory;

    /**
     * @var MembershipsFactory
     */
    protected $membershipsFactory;

    /**
     * @var MerchantCategoryCodeFactory
     */
    protected $merchantCategoryCodeFactory;

    /**
     * @var Logger
     */
    protected $logger;

    /**
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
     * @param $order Order
     * @return array
     */
    public function __invoke($order)
    {
        $signifydOrder = [];

        try {
            $purchase = $this->purchaseFactory->create();
            $userAccount = $this->userAccountFactory->create();
            $coverageRequests = $this->coverageRequestsFactory->create();
            $device = $this->deviceFactory->create();
            $merchantPlatform = $this->merchantPlatformFactory->create();
            $signifydClient = $this->signifydClientFactory->create();
            $transactions = $this->transactionsFactory->create();
            $tags = $this->tagsFactory->create();
            $sellers = $this->sellersFactory->create();
            $customerOrderRecommendation = $this->customerOrderRecommendationFactory->create();
            $memberships = $this->membershipsFactory->create();
            $merchantCategoryCode = $this->merchantCategoryCodeFactory->create();

            $signifydOrder['orderId'] = $order->getIncrementId();
            $signifydOrder['purchase'] = $purchase($order);
            $signifydOrder['userAccount'] = $userAccount($order);
            $signifydOrder['memberships'] = $memberships();
            $signifydOrder['coverageRequests'] = $coverageRequests($order->getPayment()->getMethod());
            $signifydOrder['merchantCategoryCode'] = $merchantCategoryCode();
            $signifydOrder['device'] = $device($order->getQuoteId(), $order->getStoreId(), $order);
            $signifydOrder['merchantPlatform'] = $merchantPlatform();
            $signifydOrder['signifydClient'] = $signifydClient();
            $signifydOrder['transactions'] = $transactions($order);
            $signifydOrder['sellers'] = $sellers();
            $signifydOrder['tags'] = $tags($order->getStoreId());
            $signifydOrder['customerOrderRecommendation'] = $customerOrderRecommendation();

            /**
             * This registry entry it's used to collect data from some payment methods like Payflow Link
             * It must be unregistered after use
             * @see \Signifyd\Connect\Plugin\Magento\Paypal\Model\Payflowlink
             */
            $this->registry->unregister('signifyd_payment_data');
        } catch (\Exception $e) {
            $this->logger->info("Failed to create checkout order " . $e->getMessage());
        } catch (\Error $e) {
            $this->logger->info("Failed to create checkout order " . $e->getMessage());
        }

        return $signifydOrder;
    }
}