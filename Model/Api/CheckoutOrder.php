<?php

namespace Signifyd\Connect\Model\Api;

use Signifyd\Connect\Model\Registry;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResourceModel;
use Signifyd\Connect\Helper\ConfigHelper;
use Signifyd\Connect\Logger\Logger;

class CheckoutOrder
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
     * @var QuoteResourceModel
     */
    public $quoteResourceModel;

    /**
     * @var JsonSerializer
     */
    public $jsonSerializer;

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
     * @var TagsFactory
     */
    public $tagsFactory;

    /**
     * @var AddressFactory
     */
    public $addressFactory;

    /**
     * @var SellersFactory
     */
    public $sellersFactory;

    /**
     * @var CustomerOrderRecommendationFactory
     */
    public $customerOrderRecommendationFactory;

    /**
     * @var SourceAccountDetailsFactory
     */
    public $sourceAccountDetailsFactory;

    /**
     * @var AcquirerDetailsFactory
     */
    public $acquirerDetailsFactory;

    /**
     * @var MembershipsFactory
     */
    public $membershipsFactory;

    /**
     * @var MerchantCategoryCodeFactory
     */
    public $merchantCategoryCodeFactory;

    /**
     * @var PaymentMethodFactory
     */
    public $paymentMethodFactory;

    /**
     * @var ConfigHelper
     */
    public $configHelper;

    /**
     * @var Logger
     */
    public $logger;

    /**
     * @var DecisionMechanismFactory
     */
    public $decisionMechanismFactory;

    /**
     * @param Registry $registry
     * @param PurchaseFactory $purchaseFactory
     * @param UserAccountFactory $userAccountFactory
     * @param CoverageRequestsFactory $coverageRequestsFactory
     * @param QuoteResourceModel $quoteResourceModel
     * @param JsonSerializer $jsonSerializer
     * @param DeviceFactory $deviceFactory
     * @param MerchantPlatformFactory $merchantPlatformFactory
     * @param SignifydClientFactory $signifydClientFactory
     * @param TagsFactory $tagsFactory
     * @param AddressFactory $addressFactory
     * @param SellersFactory $sellersFactory
     * @param CustomerOrderRecommendationFactory $customerOrderRecommendationFactory
     * @param SourceAccountDetailsFactory $sourceAccountDetailsFactory
     * @param AcquirerDetailsFactory $acquirerDetailsFactory
     * @param MembershipsFactory $membershipsFactory
     * @param MerchantCategoryCodeFactory $merchantCategoryCodeFactory
     * @param PaymentMethodFactory $paymentMethodFactory
     * @param ConfigHelper $configHelper
     * @param Logger $logger
     * @param DecisionMechanismFactory $decisionMechanismFactory
     */
    public function __construct(
        Registry $registry,
        PurchaseFactory $purchaseFactory,
        UserAccountFactory $userAccountFactory,
        CoverageRequestsFactory $coverageRequestsFactory,
        QuoteResourceModel $quoteResourceModel,
        JsonSerializer $jsonSerializer,
        DeviceFactory $deviceFactory,
        MerchantPlatformFactory $merchantPlatformFactory,
        SignifydClientFactory $signifydClientFactory,
        TagsFactory $tagsFactory,
        AddressFactory $addressFactory,
        SellersFactory $sellersFactory,
        CustomerOrderRecommendationFactory $customerOrderRecommendationFactory,
        SourceAccountDetailsFactory $sourceAccountDetailsFactory,
        AcquirerDetailsFactory $acquirerDetailsFactory,
        MembershipsFactory $membershipsFactory,
        MerchantCategoryCodeFactory $merchantCategoryCodeFactory,
        PaymentMethodFactory $paymentMethodFactory,
        ConfigHelper $configHelper,
        Logger $logger,
        DecisionMechanismFactory $decisionMechanismFactory
    ) {
        $this->registry = $registry;
        $this->purchaseFactory = $purchaseFactory;
        $this->userAccountFactory = $userAccountFactory;
        $this->coverageRequestsFactory = $coverageRequestsFactory;
        $this->quoteResourceModel = $quoteResourceModel;
        $this->jsonSerializer = $jsonSerializer;
        $this->deviceFactory = $deviceFactory;
        $this->merchantPlatformFactory = $merchantPlatformFactory;
        $this->signifydClientFactory = $signifydClientFactory;
        $this->tagsFactory = $tagsFactory;
        $this->addressFactory = $addressFactory;
        $this->sellersFactory = $sellersFactory;
        $this->customerOrderRecommendationFactory = $customerOrderRecommendationFactory;
        $this->sourceAccountDetailsFactory = $sourceAccountDetailsFactory;
        $this->acquirerDetailsFactory = $acquirerDetailsFactory;
        $this->membershipsFactory = $membershipsFactory;
        $this->merchantCategoryCodeFactory = $merchantCategoryCodeFactory;
        $this->paymentMethodFactory = $paymentMethodFactory;
        $this->configHelper = $configHelper;
        $this->logger = $logger;
        $this->decisionMechanismFactory = $decisionMechanismFactory;
    }

    /**
     * Construct a new signifyd Order object
     *
     * @param Quote $quote
     * @param mixed $checkoutPaymentDetails
     * @param mixed $paymentMethod
     * @return array
     */
    public function __invoke(Quote $quote, $checkoutPaymentDetails = null, $paymentMethod = null)
    {
        $signifydOrder = [];

        try {
            $reservedOrderId = $quote->getReservedOrderId();
            $purchase = $this->purchaseFactory->create();
            $userAccount = $this->userAccountFactory->create();
            $coverageRequests = $this->coverageRequestsFactory->create();
            $device = $this->deviceFactory->create();
            $merchantPlatform = $this->merchantPlatformFactory->create();
            $signifydClient = $this->signifydClientFactory->create();
            $tags = $this->tagsFactory->create();
            $address = $this->addressFactory->create();
            $sellers = $this->sellersFactory->create();
            $customerOrderRecommendation = $this->customerOrderRecommendationFactory->create();
            $memberships = $this->membershipsFactory->create();
            $merchantCategoryCode = $this->merchantCategoryCodeFactory->create();

            if (empty($reservedOrderId)) {
                $quote->reserveOrderId();
                $reservedOrderId = $quote->getReservedOrderId();
                $this->quoteResourceModel->save($quote);
            }

            $signifydOrder['orderId'] = $reservedOrderId;
            $signifydOrder['purchase'] = $purchase($quote);
            $signifydOrder['userAccount'] = $userAccount($quote);
            $signifydOrder['memberships'] = $memberships();
            $signifydOrder['coverageRequests'] = $coverageRequests($paymentMethod);
            $signifydOrder['merchantCategoryCode'] = $merchantCategoryCode();
            $signifydOrder['device'] = $device($quote->getId(), $quote->getStore());
            $signifydOrder['merchantPlatform'] = $merchantPlatform();
            $signifydOrder['signifydClient'] = $signifydClient();
            $signifydOrder['sellers'] = $sellers();
            $signifydOrder['tags'] = $tags($quote->getStoreId());
            $signifydOrder['customerOrderRecommendation'] = $customerOrderRecommendation();
            $signifydOrder['decisionMechanism'] = ($this->decisionMechanismFactory->create())();

            $policyConfig = $this->configHelper->getPolicyName(
                $quote->getStore()->getScopeType(),
                $quote->getStoreId()
            );
            $policyFromMethod = $this->configHelper->getPolicyFromMethod(
                $policyConfig,
                $paymentMethod,
                $quote->getStore()->getScopeType(),
                $quote->getStoreId()
            );
            $evalRequest = ($policyFromMethod == 'SCA_PRE_AUTH') ? ['SCA_EVALUATION'] : null;
            $signifydOrder['additionalEvalRequests'] = $evalRequest;
            $signifydOrder['checkoutId'] = sha1($this->jsonSerializer->serialize($signifydOrder));
            $transactions = [];
            $sourceAccountDetails = $this->sourceAccountDetailsFactory->create();
            $acquirerDetails = $this->acquirerDetailsFactory->create();

            if (isset($paymentMethod)) {
                $transaction = [];
                $billingAddres = $quote->getBillingAddress();
                $makePaymentMethod = $this->paymentMethodFactory->create();
                $transaction['checkoutPaymentDetails']['billingAddress'] = $address($billingAddres);
                $transaction['currency'] = $quote->getBaseCurrencyCode();
                $transaction['amount'] = $quote->getGrandTotal();
                $transaction['sourceAccountDetails'] = $sourceAccountDetails();
                $transaction['acquirerDetails'] = $acquirerDetails();
                $transaction['paymentMethod'] = $makePaymentMethod($quote);
                $transaction['gateway'] = $paymentMethod;

                if (is_array($checkoutPaymentDetails) && empty($checkoutPaymentDetails) === false
                ) {
                    $transaction['checkoutPaymentDetails']['cardBin'] =
                        $checkoutPaymentDetails['cardBin'];
                    $transaction['checkoutPaymentDetails']['accountHolderName'] =
                        $checkoutPaymentDetails['holderName'];
                    $transaction['checkoutPaymentDetails']['cardLast4'] =
                        $checkoutPaymentDetails['cardLast4'];
                    $transaction['checkoutPaymentDetails']['cardExpiryMonth'] =
                        $checkoutPaymentDetails['cardExpiryMonth'];
                    $transaction['checkoutPaymentDetails']['cardExpiryYear'] =
                        $checkoutPaymentDetails['cardExpiryYear'];
                }

                $transactions[] = $transaction;
            }

            $signifydOrder['transactions'] = $transactions;

            /**
             * This registry entry it's used to collect data from some payment methods like Payflow Link
             * It must be unregistered after use
             * @see \Signifyd\Connect\Plugin\Magento\Paypal\Model\Payflowlink
             */
            $this->registry->setData('signifyd_payment_data');
        } catch (\Exception $e) {
            $this->logger->info("Failed to create checkout order " . $e->getMessage(), ['entity' => $quote]);
        } catch (\Error $e) {
            $this->logger->info("Failed to create checkout order " . $e->getMessage(), ['entity' => $quote]);
        }

        return $signifydOrder;
    }
}
