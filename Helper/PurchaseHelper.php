<?php
/**
 * Copyright 2015 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Signifyd\Connect\Helper;

use Braintree\Exception;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResourceModel;
use Magento\Sales\Model\Order;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order\Address;
use Magento\Sales\Model\Order\Item;
use Magento\Sales\Model\ResourceModel\Order as OrderResourceModel;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Framework\App\ProductMetadata;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\ResourceModel\Customer as CustomerResourceModel;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Store\Model\ScopeInterface;
use Signifyd\Connect\Model\PaymentVerificationFactory;
use Magento\Framework\Registry;
use Signifyd\Connect\Model\ResourceModel\Casedata as CasedataResourceModel;
use Signifyd\Connect\Model\CasedataFactory;
use Signifyd\Models\GuaranteeFactory as GuaranteeModelFactory;
use Signifyd\Connect\Logger\Logger;
use Magento\Framework\App\ResourceConnection;
use Magento\Sales\Api\Data\TransactionSearchResultInterfaceFactory;
use Magento\Sales\Model\ResourceModel\Order\Payment\Transaction\CollectionFactory as TransactionCollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Catalog\Model\ResourceModel\Category as CategoryResourceModel;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Stdlib\DateTime\DateTimeFactory;
use Magento\Directory\Model\RegionFactory;
use Magento\Directory\Model\ResourceModel\Region as RegionResourceModel;

/**
 * Class PurchaseHelper
 * Handles the conversion from Magento Order to Signifyd Case and sends to Signifyd service.
 */
class PurchaseHelper
{
    /**
     * @var OrderResourceModel
     */
    protected $orderResourceModel;

    /**
     * @var RemoteAddress
     */
    protected $remoteAddress;

    /**
     * @var ProductMetadata
     */
    protected $productMetadata;

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
     * @var Logger
     */
    protected $logger;

    /**
     * @var \Signifyd\Connect\Helper\ConfigHelper
     */
    protected $configHelper;

    /**
     * @var \Magento\Framework\Module\ModuleListInterface
     */
    protected $moduleList;

    /**
     * @var \Signifyd\Connect\Helper\DeviceHelper
     */
    protected $deviceHelper;

    /**
     * @var PaymentVerificationFactory
     */
    protected $paymentVerificationFactory;

    /**
     * @var Registry
     */
    protected $registry;

    /**
     * @var OrderHelper
     */
    protected $orderHelper;

    /**
     * @var CasedataFactory
     */
    protected $casedataFactory;

    /**
     * @var CasedataResourceModel
     */
    protected $casedataResourceModel;

    /**
     * @var ResourceConnection
     */
    protected $resourceConnection;

    /**
     * @var GuaranteeModelFactory
     */
    protected $guaranteeModelFactory;

    /**
     * @var TransactionSearchResultInterfaceFactory
     */
    protected $transactions;

    /**
     * @var TransactionCollectionFactory
     */
    protected $transactionCollectionFactory;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfigInterface;

    /**
     * @var JsonSerializer
     */
    protected $jsonSerializer;

    /**
     * @var CategoryCollectionFactory
     */
    protected $categoryCollectionFactory;

    /**
     * @var CategoryFactory
     */
    protected $categoryFactory;

    /**
     * @var CategoryResourceModel
     */
    protected $categoryResourceModel;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManagerInterface;

    /**
     * @var QuoteResourceModel
     */
    protected $quoteResourceModel;

    /**
     * @var DateTimeFactory
     */
    protected $dateTimeFactory;

    /**
     * @var ProductMetadataInterface
     */
    protected $productMetadataInterface;

    /**
     * @var RegionFactory
     */
    protected $regionFactory;

    /**
     * @var RegionResourceModel
     */
    protected $regionResourceModel;

    /**
     * PurchaseHelper constructor.
     * @param OrderResourceModel $orderResourceModel
     * @param RemoteAddress $remoteAddress
     * @param ProductMetadata $productMetadata
     * @param CustomerFactory $customerFactory
     * @param CustomerResourceModel $customerResourceModel
     * @param OrderCollectionFactory $orderCollectionFactory
     * @param Logger $logger
     * @param ConfigHelper $configHelper
     * @param ModuleListInterface $moduleList
     * @param DeviceHelper $deviceHelper
     * @param PaymentVerificationFactory $paymentVerificationFactory
     * @param Registry $registry
     * @param OrderHelper $orderHelper
     * @param CasedataFactory $casedataFactory
     * @param CasedataResourceModel $casedataResourceModel
     * @param ResourceConnection $resourceConnection
     * @param GuaranteeModelFactory $guaranteeModelFactory
     * @param TransactionSearchResultInterfaceFactory $transactions
     * @param TransactionCollectionFactory $transactionCollectionFactory
     * @param ScopeConfigInterface $scopeConfigInterface
     * @param JsonSerializer $jsonSerializer
     * @param CategoryCollectionFactory $categoryCollectionFactory
     * @param CategoryFactory $categoryFactory
     * @param CategoryResourceModel $categoryResourceModel
     * @param StoreManagerInterface $storeManagerInterface
     * @param QuoteResourceModel $quoteResourceModel
     * @param DateTimeFactory $dateTimeFactory
     * @param ProductMetadataInterface $productMetadataInterface
     * @param RegionFactory $regionFactory
     * @param RegionResourceModel $regionResourceModel
     */
    public function __construct(
        OrderResourceModel $orderResourceModel,
        RemoteAddress $remoteAddress,
        ProductMetadata $productMetadata,
        CustomerFactory $customerFactory,
        CustomerResourceModel $customerResourceModel,
        OrderCollectionFactory $orderCollectionFactory,
        Logger $logger,
        ConfigHelper $configHelper,
        ModuleListInterface $moduleList,
        DeviceHelper $deviceHelper,
        PaymentVerificationFactory $paymentVerificationFactory,
        Registry $registry,
        OrderHelper $orderHelper,
        CasedataFactory $casedataFactory,
        CasedataResourceModel $casedataResourceModel,
        ResourceConnection $resourceConnection,
        GuaranteeModelFactory $guaranteeModelFactory,
        TransactionSearchResultInterfaceFactory $transactions,
        TransactionCollectionFactory $transactionCollectionFactory,
        ScopeConfigInterface $scopeConfigInterface,
        JsonSerializer $jsonSerializer,
        CategoryCollectionFactory $categoryCollectionFactory,
        CategoryFactory $categoryFactory,
        CategoryResourceModel $categoryResourceModel,
        StoreManagerInterface $storeManagerInterface,
        QuoteResourceModel $quoteResourceModel,
        DateTimeFactory $dateTimeFactory,
        ProductMetadataInterface $productMetadataInterface,
        RegionFactory $regionFactory,
        RegionResourceModel $regionResourceModel
    ) {
        $this->orderResourceModel = $orderResourceModel;
        $this->remoteAddress = $remoteAddress;
        $this->productMetadata = $productMetadata;
        $this->customerFactory = $customerFactory;
        $this->customerResourceModel = $customerResourceModel;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->logger = $logger;
        $this->moduleList = $moduleList;
        $this->deviceHelper = $deviceHelper;
        $this->paymentVerificationFactory = $paymentVerificationFactory;
        $this->registry = $registry;
        $this->configHelper = $configHelper;
        $this->orderHelper = $orderHelper;
        $this->casedataFactory = $casedataFactory;
        $this->casedataResourceModel = $casedataResourceModel;
        $this->resourceConnection = $resourceConnection;
        $this->guaranteeModelFactory = $guaranteeModelFactory;
        $this->transactions = $transactions;
        $this->transactionCollectionFactory = $transactionCollectionFactory;
        $this->scopeConfigInterface = $scopeConfigInterface;
        $this->jsonSerializer = $jsonSerializer;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->categoryFactory = $categoryFactory;
        $this->categoryResourceModel = $categoryResourceModel;
        $this->storeManagerInterface = $storeManagerInterface;
        $this->quoteResourceModel = $quoteResourceModel;
        $this->dateTimeFactory = $dateTimeFactory;
        $this->productMetadataInterface = $productMetadataInterface;
        $this->regionFactory = $regionFactory;
        $this->regionResourceModel = $regionResourceModel;
    }

    /**
     * Getting the ip address of the order
     * @param Order $order
     * @return mixed
     */
    public function getIPAddress(Order $order)
    {
        if ($order->getRemoteIp()) {
            if ($order->getXForwardedFor()) {
                return $this->filterIp($order->getXForwardedFor());
            }

            return $this->filterIp($order->getRemoteIp());
        }

        return $this->filterIp($this->remoteAddress->getRemoteAddress());
    }

    /**
     * Filter the ip address
     * @param $ip
     * @return mixed
     */
    public function filterIp($ipString)
    {
        $matches = [];

        $pattern = '(([0-9]{1,3}(?:\.[0-9]{1,3}){3})|([0-9a-fA-F]{1,4}:){7,7}[0-9a-fA-F]{1,4}|' .
            '([0-9a-fA-F]{1,4}:){1,7}:|([0-9a-fA-F]{1,4}:){1,6}:[0-9a-fA-F]{1,4}|' .
            '([0-9a-fA-F]{1,4}:){1,5}(:[0-9a-fA-F]{1,4}){1,2}|([0-9a-fA-F]{1,4}:){1,4}(:[0-9a-fA-F]{1,4}){1,3}|' .
            '([0-9a-fA-F]{1,4}:){1,3}(:[0-9a-fA-F]{1,4}){1,4}|([0-9a-fA-F]{1,4}:){1,2}(:[0-9a-fA-F]{1,4}){1,5}|' .
            '[0-9a-fA-F]{1,4}:((:[0-9a-fA-F]{1,4}){1,6})|:((:[0-9a-fA-F]{1,4}){1,7}|:)|' .
            'fe80:(:[0-9a-fA-F]{0,4}){0,4}%[0-9a-zA-Z]{1,}|::(ffff(:0{1,4}){0,1}:){0,1}((25[0-5]|' .
            '(2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])|' .
            '([0-9a-fA-F]{1,4}:){1,4}:((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(25[0-5]|' .
            '(2[0-4]|1{0,1}[0-9]){0,1}[0-9]))';

        preg_match_all($pattern, $ipString, $matches);

        if (isset($matches[0]) && isset($matches[0][0])) {
            return $matches[0][0];
        }

        return null;
    }

    /**
     * @param Item $item
     * @return array
     */
    public function makeProduct(Item $item)
    {
        $product = $item->getProduct();
        $productImageUrl = $this->getProductImage($product);

        $productCategorysId = $product->getCategoryIds();
        $categoryCollection = $this->categoryCollectionFactory->create()
            ->addFieldToFilter('entity_id', ['in' => $productCategorysId])
            ->addFieldToFilter('level', ['neq' => 0])
            ->setOrder('position', 'ASC')
            ->setOrder('level', 'ASC');
        $productCategoryId = null;
        $productSubCategoryId = null;

        /** @var \Magento\Catalog\Model\Category $category */
        foreach ($categoryCollection as $category) {
            if (isset($productCategoryId) && isset($productSubCategoryId)) {
                break;
            }

            switch ($category->getLevel()) {
                case 2:
                    $productCategoryId = $category->getId();
                    break;
                case 3:
                    $productSubCategoryId = $category->getId();
                    break;
            }
        }

        if (isset($productCategoryId)) {
            /** @var \Magento\Catalog\Model\Category $mainCategory */
            $mainCategory = $this->categoryFactory->create();
            $this->categoryResourceModel->load($mainCategory, $productCategoryId);
            $mainCategoryName = $mainCategory->getName();
        } else {
            $mainCategoryName = null;
        }

        if (isset($productSubCategoryId)) {
            /** @var \Magento\Catalog\Model\Category $subCategory */
            $subCategory = $this->categoryFactory->create();
            $this->categoryResourceModel->load($subCategory, $productSubCategoryId);
            $subCategoryName = $subCategory->getName();
        } else {
            $subCategoryName = null;
        }

        $itemPrice = floatval(number_format($item->getPriceInclTax(), 2, '.', ''));

        if ($itemPrice <= 0) {
            if ($item->getParentItem()) {
                if ($item->getParentItem()->getProductType() === 'configurable') {
                    $itemPrice = floatval(number_format($item->getParentItem()->getPriceInclTax(), 2, '.', ''));
                }
            }
        }

        $product = [];
        $product['itemName'] = $item->getName();
        $product['itemPrice'] = $itemPrice;
        $product['itemQuantity'] = (int)$item->getQtyOrdered();
        $product['itemIsDigital'] = (bool) $item->getIsVirtual();
        $product['itemCategory'] = $mainCategoryName;
        $product['itemSubCategory'] = $subCategoryName;
        $product['itemId'] = $item->getSku();
        $product['itemImage'] = $productImageUrl;
        $product['itemUrl'] = $item->getProduct()->getProductUrl();
        $product['itemWeight'] = $item->getProduct()->getWeight();
        $product['shipmentId'] = null;
        $product['subscription'] = $this->makeSubscription();

        return $product;
    }

    public function getProductImage($product)
    {
        $productImage = $product->getImage();

        if (isset($productImage)) {
            $productImageUrl = $this->storeManagerInterface->getStore()
                    ->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA) . 'catalog/product' . $productImage;
        } else {
            $productImageUrl = null;
        }

        return $productImageUrl;
    }

    /**
     * @param $paymentMethod
     * @return array|string[]|null
     */
    public function getDecisionRequest($paymentMethod = null)
    {
        if ($this->configHelper->isScoreOnly()) {
            return ["NONE"];
        }

        $configDecision = $this->configHelper->getDecisionRequest();

        if (isset($configDecision) === false) {
            return null;
        }

        return $this->getDecisionForMethod($configDecision, $paymentMethod);
    }

    /**
     * getReceivedBy method should be extended/intercepted by plugin to add value to it.
     * If the order was was placed on-behalf of a customer service or sales agent, his or her name.
     *
     * @return null
     */
    public function getReceivedBy()
    {
        return null;
    }

    /**
     * getHolderTaxId method should be extended/intercepted by plugin to add value to it.
     * The unique taxpayer identifier for the account holder. Due to legal restrictions,
     * the only values currently accepted here are Brazilian CPF numbers. All other values provided will be rejected.
     *
     * @return null
     */
    public function getHolderTaxId()
    {
        return null;
    }

    /**
     * getHolderTaxCountry method should be extended/intercepted by plugin to add value to it.
     * The country that issued the holderTaxId. Due to legal restrictions, the only value currently accepted here is BR.
     *
     * @return null
     */
    public function getHolderTaxCountry()
    {
        return null;
    }

    /**
     * getParentTransactionId method should be extended/intercepted by plugin to add value to it.
     * If there was a previous transaction for the payment like a partial AUTHORIZATION or SALE,
     * the parent id should include the originating transaction id.
     *
     * @return null
     */
    public function getParentTransactionId()
    {
        return null;
    }

    /**
     * getGatewayStatusMessage method should be extended/intercepted by plugin to add value to it.
     * Additional information provided by the payment provider describing why the transaction succeeded or failed.
     *
     * @return null
     */
    public function getGatewayStatusMessage()
    {
        return null;
    }

    /**
     * getGatewayErrorCode method should be extended/intercepted by plugin to add value to it.
     * If the transaction resulted in an error or failure the enumerated reason
     * the transcaction failed as provided by the payment provider.
     *
     * @return null
     */
    public function getGatewayErrorCode()
    {
        return null;
    }

    /**
     * getPaypalPendingReasonCode method should be extended/intercepted by plugin to add value to it.
     * The response provided in reason_code by Paypal if the payment_status is Pending.
     * This field does not apply to capturing point-of-sale authorizations, which do not create pending payments.
     *
     * @return null
     */
    public function getPaypalPendingReasonCode()
    {
        return null;
    }

    /**
     * getPaypalProtectionEligibility method should be extended/intercepted by plugin to add value to it.
     * The response provided by Paypal for protection_eligibility.
     * The merchant protection level in effect for the transaction. Supported only for PayPal payments.
     *
     * @return null
     */
    public function getPaypalProtectionEligibility()
    {
        return null;
    }

    /**
     * getPaypalProtectionEligibilityType method should be extended/intercepted by plugin to add value to it.
     * The response provided by Paypal for protection_eligibility_type.
     * The merchant protection type in effect for the transaction.
     * Returned only when protection_eligibility is ELIGIBLE or PARTIALLY_ELIGIBLE.
     * Supported only for PayPal payments.
     *
     * @return null
     */
    public function getPaypalProtectionEligibilityType()
    {
        return null;
    }

    public function getVerifications($order)
    {
        $verifications = [];
        $verifications['avsResponseCode'] = $this->getAvsCode($order);
        $verifications['cvvResponseCode'] = $this->getCvvCode($order);
        return $verifications;
    }

    /**
     * getSellers method should be extended/intercepted by plugin to add value to it.
     * Use only if you operate a marketplace (e.g. Ebay)
     * and allow other merchants to list and sell products on the online store.
     *
     * @return null
     */
    public function getSellers()
    {
        return null;
    }

    /**
     * @param $storeId
     * @return string[]|null
     */
    public function getTags($storeId)
    {
        $enabledConfig = $this->scopeConfigInterface->getValue(
            'signifyd/general/enabled',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
            $storeId
        );

        if ($enabledConfig == 'passive') {
            return ['Passive Mode'];
        }

        return null;
    }

    /**
     * customerOrderRecommendation field it is part of enterprise APIs
     * and this method should be extended/intercepted by plugin to add value to it
     *
     * @return null
     */
    public function getCustomerOrderRecommendation()
    {
        return null;
    }

    /**
     * deviceFingerprints field it is part of enterprise APIs
     * and this method should be extended/intercepted by plugin to add value to it
     *
     * @return null
     */
    public function getDeviceFingerprints()
    {
        return null;
    }

    /**
     * sellerAccountNumber field it is part of enterprise APIs
     * and this method should be extended/intercepted by plugin to add value to it
     *
     * @return null
     */
    public function getSellerAccountNumber()
    {
        return null;
    }

    /**
     * isDeliverable field it is part of enterprise APIs
     * and this method should be extended/intercepted by plugin to add value to it
     *
     * @return null
     */
    public function getIsDeliverable()
    {
        return null;
    }

    /**
     * isReceivingMail field it is part of enterprise APIs
     * and this method should be extended/intercepted by plugin to add value to it
     *
     * @return null
     */
    public function getIsReceivingMail()
    {
        return null;
    }

    /**
     * type field it is part of enterprise APIs
     * and this method should be extended/intercepted by plugin to add value to it
     *
     * @return null
     */
    public function getType()
    {
        return null;
    }

    /**
     * deliveryPoint field it is part of enterprise APIs
     * and this method should be extended/intercepted by plugin to add value to it
     *
     * @return null
     */
    public function getDeliveryPoint()
    {
        return null;
    }

    /**
     * @param $order Order
     * @return array
     */
    public function makePurchase(Order $order)
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
        $purchase['confirmationPhone'] = $order->getCustomerEmail();
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
     * @param Order $order
     * @return array
     */
    public function makeShipments(Order $order)
    {
        $shipments = [];
        $shippingMethod = $order->getShippingMethod(true);

        $shipment = [];
        $shipment['destination'] = $this->makeRecipient($order);
        $shipment['origin'] = $this->makeOrigin($order->getStoreId());
        $shipment['carrier'] = $this->makeShipper($shippingMethod);
        ;
        $shipment['minDeliveryDate'] = $this->makeMinDeliveryDate();
        $shipment['maxDeliveryDate'] = null;
        $shipment['shipmentId'] = null;
        $shipment['fulfillmentMethod'] = $this->makeFulfillmentMethod();

        $shipments[] = $shipment;

        foreach ($order->getItems() as $item) {
            if ($item->getProductType() == 'giftcard') {
                $shipmentGc = [];
                $shipmentGc['destination'] = [
                    'email' => $item->getProductOptions()['giftcard_recipient_email'],
                    'fullName' => $item->getProductOptions()['giftcard_recipient_name']
                ];

                $shipments[] = $shipmentGc;
            }
        }

        return $shipments;
    }

    /**
     * @param Quote $quote
     * @return array
     */
    public function makeShipmentsFromQuote(Quote $quote)
    {
        $shipments = [];
        $shippingMethod = $quote->getShippingAddress()->getShippingMethod();

        $shipment = [];
        $shipment['destination'] = $this->makeRecipientFromQuote($quote);
        $shipment['origin'] = $this->makeOrigin($quote->getStoreId());
        $shipment['carrier'] = $shippingMethod ? $this->makeShipper($shippingMethod) : null;
        $shipment['minDeliveryDate'] = $this->makeMinDeliveryDate();
        $shipment['maxDeliveryDate'] = null;
        $shipment['shipmentId'] = null;
        $shipment['fulfillmentMethod'] = $this->makeFulfillmentMethod();

        $shipments[] = $shipment;

        foreach ($quote->getItems() as $item) {
            if ($item->getProductType() == 'giftcard') {
                $shipmentGc = [];
                $shipmentGc['destination'] = [
                    'email' => $item->getProductOptions()['giftcard_recipient_email'],
                    'fullName' => $item->getProductOptions()['giftcard_recipient_name']
                ];

                $shipments[] = $shipmentGc;
            }
        }

        return $shipments;
    }

    public function makeShipper($shippingMethod)
    {
        if (isset($shippingMethod) === false) {
            return null;
        }

        if (is_string($shippingMethod)) {
            $shippingMethodArray = explode('_', $shippingMethod);

            if (count($shippingMethodArray) < 2) {
                return null;
            }

            $shippingCarrier = $shippingMethodArray[0];
        } else {
            $shippingCarrier = $shippingMethod->getCarrierCode();
        }

        $allowMethodsJson = $this->scopeConfigInterface->getValue('signifyd/general/shipper_config');
        $allowMethods = $this->jsonSerializer->unserialize($allowMethodsJson);

        foreach ($allowMethods as $i => $allowMethod) {
            if (in_array($shippingCarrier, $allowMethod)) {
                return $i;
            }
        }

        return null;
    }

    public function makeshippingMethod($shippingMethod)
    {
        if (is_string($shippingMethod)) {
            $shippingMethodArray = explode('_', $shippingMethod);

            if (count($shippingMethodArray) < 2) {
                return false;
            }

            $shippingMethodCode = $shippingMethodArray[1];
        } else {
            $shippingMethodCode = $shippingMethod->getMethod();
        }

        $allowMethodsJson = $this->scopeConfigInterface->getValue('signifyd/general/shipping_method_config');
        $allowMethods = $this->jsonSerializer->unserialize($allowMethodsJson);

        foreach ($allowMethods as $i => $allowMethod) {
            if (in_array($shippingMethodCode, $allowMethod)) {
                return $i;
            }
        }

        return false;
    }

    /**
     * @param $mageAddress
     * @return array
     */
    public function formatSignifydAddress($mageAddress)
    {
        $address = [];

        $address['streetAddress'] = $mageAddress->getStreetLine(1);
        $address['unit'] = $mageAddress->getStreetLine(2);
        $address['postalCode'] = $mageAddress->getPostcode();
        $address['city'] = $mageAddress->getCity();
        $address['provinceCode'] = $mageAddress->getRegionCode();
        $address['countryCode'] = $mageAddress->getCountryId();

        return $address;
    }

    /**
     * @param $order Order
     * @return array
     */
    public function makeRecipient(Order $order)
    {
        $recipient = [];
        $address = $order->getShippingAddress();

        if ($address !== null) {
            $recipient['fullName'] = $address->getName();
            $recipient['organization'] = $address->getCompany();
            $recipient['address'] = $this->formatSignifydAddress($address);
        } else {
            $recipient['email'] = $order->getCustomerEmail();
        }

        if (empty($recipient['fullName'])) {
            $recipient['fullName'] = $order->getCustomerName();
        }

        return $recipient;
    }

    public function makeOrigin($storeId)
    {
        $streetAddress = $this->scopeConfigInterface->getValue(
            'general/store_information/street_line1',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
            $storeId
        );

        $postalCode = $this->scopeConfigInterface->getValue(
            'general/store_information/postcode',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
            $storeId
        );

        $city = $this->scopeConfigInterface->getValue(
            'general/store_information/city',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
            $storeId
        );

        $provinceId = $this->scopeConfigInterface->getValue(
            'general/store_information/region_id',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
            $storeId
        );

        $countryCode = $this->scopeConfigInterface->getValue(
            'general/store_information/country_id',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
            $storeId
        );

        if (isset($streetAddress) === false ||
            isset($postalCode) === false ||
            isset($city) === false
        ) {
            return null;
        }

        if (isset($provinceId)) {
            $magentoRegion = $this->regionFactory->create();
            $this->regionResourceModel->load($magentoRegion, $provinceId);
            $provinceCode = $magentoRegion->getCode();
        } else {
            $provinceCode = null;
        }

        $origin = [];
        $origin['locationId'] = $storeId;
        $origin['address'] = [
            'streetAddress' => $streetAddress,
            'postalCode' => $postalCode,
            'city' => $city,
            'provinceCode' => $provinceCode,
            'countryCode' => $countryCode ?? null
        ];

        return $origin;
    }

    public function makeTransactions(Order $order)
    {
        $lastTransaction = $order->getPayment()->getLastTransId();
        $transactionsFromOrder = $this->transactionCollectionFactory->create()
            ->addFieldToFilter('txn_id', ['eq' => $lastTransaction]);
        $transactionFromOrder = $transactionsFromOrder->getFirstItem();

        $transactions = [];
        $lastTransaction = [];

        if ($transactionFromOrder->isEmpty()) {
            $dateTime = $this->dateTimeFactory->create();
            $transactionDate = $dateTime->gmtDate();
        } else {
            $transactionDate = $transactionFromOrder->getData('created_at');
        }

        $transactionId = $this->getTransactionId($order);

        $lastTransaction['gatewayStatusCode'] = 'SUCCESS';
        $lastTransaction['paymentMethod'] = $this->makePaymentMethod($order->getPayment()->getMethod());
        $lastTransaction['checkoutPaymentDetails'] = $this->makeCheckoutPaymentDetails($order);
        $lastTransaction['amount'] = $order->getGrandTotal();
        $lastTransaction['currency'] = $order->getOrderCurrencyCode();
        $lastTransaction['gateway'] = $order->getPayment()->getMethod();
        $lastTransaction['sourceAccountDetails'] = $this->makeSourceAccountDetails();
        $lastTransaction['acquirerDetails'] = $this->makeAcquirerDetails();
        $lastTransaction['gatewayErrorCode'] = $this->getGatewayErrorCode();
        $lastTransaction['gatewayStatusMessage'] = $this->getGatewayStatusMessage();
        $lastTransaction['createdAt'] = date('c', strtotime($transactionDate));
        $lastTransaction['parentTransactionId'] = $this->getParentTransactionId();
        $lastTransaction['scaExemptionRequested'] = $this->makeScaExemptionRequested();
        $lastTransaction['verifications'] = $this->getVerifications($order);
        $lastTransaction['threeDsResult'] = $this->makeThreeDsResult();
        $lastTransaction['paypalPendingReasonCode'] = $this->getPaypalPendingReasonCode();
        $lastTransaction['paypalProtectionEligibility'] = $this->getPaypalProtectionEligibility();
        $lastTransaction['paypalProtectionEligibilityType'] = $this->getPaypalProtectionEligibilityType();

        if (isset($transactionId) === false) {
            $transactionId = sha1($this->jsonSerializer->serialize($lastTransaction));
        }

        $lastTransaction['transactionId'] = $transactionId;
        $transactions[] = $lastTransaction;

        return $transactions;
    }

    public function makeCheckoutTransactions(Quote $quote, $checkoutToken, $methodData = [])
    {
        $reservedOrderId = $quote->getReservedOrderId();

        if (empty($reservedOrderId)) {
            $quote->reserveOrderId();
            $reservedOrderId = $quote->getReservedOrderId();
            $this->quoteResourceModel->save($quote);
        }

        $checkoutTransaction = [];
        $checkoutTransaction['checkoutId'] = $checkoutToken;
        $checkoutTransaction['orderId'] = $reservedOrderId;
        $errorCode = $methodData['gatewayRefusedReason'] ?? "CARD_DECLINED";
        $gateway = $methodData['gateway'] ?? null;

        $transactions = [];
        $transaction = [];
        $transaction['gatewayStatusCode'] = 'FAILURE';
        $transaction['paymentMethod'] = $this->makePaymentMethod($quote->getPayment()->getMethod());
        $transaction['checkoutPaymentDetails'] = $this->makeCheckoutPaymentDetailsFromQuote($quote, $methodData);
        $transaction['amount'] = $quote->getGrandTotal();
        $transaction['currency'] = $quote->getBaseCurrencyCode();
        ;
        $transaction['gateway'] = $gateway;
        $transaction['sourceAccountDetails'] = $this->makeSourceAccountDetails();
        $transaction['acquirerDetails'] = $this->makeAcquirerDetails();
        $transaction['gatewayErrorCode'] = $errorCode;
        $transaction['gatewayStatusMessage'] = $this->getGatewayStatusMessage();
        $transaction['scaExemptionRequested'] = $this->makeScaExemptionRequested();
        $transaction['threeDsResult'] = $this->makeThreeDsResult();
        $transaction['paypalPendingReasonCode'] = $this->getPaypalPendingReasonCode();
        $transaction['paypalProtectionEligibility'] = $this->getPaypalProtectionEligibility();
        $transaction['paypalProtectionEligibilityType'] = $this->getPaypalProtectionEligibilityType();
        $transaction['transactionId'] = sha1($this->jsonSerializer->serialize($transaction));

        $transactions[] = $transaction;
        $checkoutTransaction['transactions'] = $transactions;

        return $checkoutTransaction;
    }

    public function makeCheckoutPaymentDetailsFromQuote(Quote $quote, $methodData = [])
    {
        $checkoutPaymentDetails = [];

        if (is_array($methodData)) {
            $checkoutPaymentDetails['cardLast4'] = $methodData['cardLast4'] ?? null;
            $checkoutPaymentDetails['cardExpiryMonth'] = $methodData['cardExpiryMonth'] ?? null;
            $checkoutPaymentDetails['cardExpiryYear'] = $methodData['cardExpiryYear'] ?? null;
        }

        $billingAddress = $quote->getBillingAddress();
        $checkoutPaymentDetails['accountHolderName'] = $this->getCardholderFromQuote($quote);
        $checkoutPaymentDetails['accountHolderTaxId'] = $this->getHolderTaxId();
        $checkoutPaymentDetails['accountHolderTaxIdCountry'] = $this->getHolderTaxCountry();
        $checkoutPaymentDetails['billingAddress'] = $this->formatSignifydAddress($billingAddress);

        return $checkoutPaymentDetails;
    }

    public function getCardholderFromQuote(Quote $quote)
    {
        try {
            $firstname = $quote->getBillingAddress()->getFirstname();
            $lastname = $quote->getBillingAddress()->getLastname();
            $cardholder = trim($firstname) . ' ' . trim($lastname);
            $cardholder = strtoupper($cardholder);
            $cardholder = preg_replace('/  +/', ' ', $cardholder);

            return $cardholder;
        } catch (Exception $e) {
            $this->logger->error('Error fetching cardholder: ' . $e->getMessage(), ['entity' => $quote]);
            return '';
        }
    }

    public function makePaymentMethod($paymentMethod)
    {
        $allowMethodsJson = $this->scopeConfigInterface->getValue('signifyd/general/payment_methods_config');
        $allowMethods = $this->jsonSerializer->unserialize($allowMethodsJson);

        foreach ($allowMethods as $i => $allowMethod) {
            if (in_array($paymentMethod, $allowMethod)) {
                return $i;
            }
        }

        return $paymentMethod;
    }

    /**
     * @param $order Order
     * @return array
     */
    public function makeCheckoutPaymentDetails(Order $order)
    {
        $billingAddress = $order->getBillingAddress();
        $checkoutPaymentDetails = [];
        $checkoutPaymentDetails['billingAddress'] = $this->formatSignifydAddress($billingAddress);
        $checkoutPaymentDetails['accountHolderName'] = $this->getCardholder($order);
        $checkoutPaymentDetails['accountHolderTaxId'] = $this->getHolderTaxId();
        $checkoutPaymentDetails['accountHolderTaxIdCountry'] = $this->getHolderTaxCountry();
        $checkoutPaymentDetails['accountLast4'] = $this->makeAccountLast4();
        $checkoutPaymentDetails['abaRoutingNumber'] = $this->makeAbaRoutingNumber();
        $checkoutPaymentDetails['cardToken'] = $this->makeCardToken();
        $checkoutPaymentDetails['cardTokenProvider'] = $this->makeCardTokenProvider();
        $checkoutPaymentDetails['cardBin'] = $this->getBin($order);
        $checkoutPaymentDetails['cardExpiryMonth'] = $this->getExpMonth($order);
        $checkoutPaymentDetails['cardExpiryYear'] = $this->getExpYear($order);
        $checkoutPaymentDetails['cardLast4'] = $this->getLast4($order);
        $checkoutPaymentDetails['cardBrand'] = $this->makeCardBrand();
        $checkoutPaymentDetails['cardInstallments'] = $this->makeCardInstallments();

        return $checkoutPaymentDetails;
    }

    /** Construct a user account blob
     * @param $order Order
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
     * Construct a new case object
     * @param $order Order
     * @return array
     */
    public function processOrderData($order)
    {
        $case = [];

        $case['orderId'] = $order->getIncrementId();
        $case['purchase'] = $this->makePurchase($order);
        $case['userAccount'] = $this->makeUserAccount($order);
        $case['memberships'] = $this->makeMemberships();
        $case['coverageRequests'] = $this->getDecisionRequest($order->getPayment()->getMethod());
        $case['merchantCategoryCode'] = $this->makeMerchantCategoryCode();
        $case['device'] = $this->makeDevice($order->getQuoteId(), $order->getStoreId());
        $case['merchantPlatform'] = $this->getMerchantPlataform();
        $case['signifydClient'] = $this->makeVersions();
        $case['transactions'] = $this->makeTransactions($order);
        $case['sellers'] = $this->getSellers();
        $case['tags'] = $this->getTags($order->getStoreId());
        $case['customerOrderRecommendation'] = $this->getCustomerOrderRecommendation();

        /**
         * This registry entry it's used to collect data from some payment methods like Payflow Link
         * It must be unregistered after use
         * @see \Signifyd\Connect\Plugin\Magento\Paypal\Model\Payflowlink
         */
        $this->registry->unregister('signifyd_payment_data');

        return $case;
    }

    public function makeVersions()
    {
        $version = [];
        $version['application'] = 'Magento 2';
        $version['version'] = (string)($this->moduleList->getOne('Signifyd_Connect')['setup_version']);

        return $version;
    }

    /**
     * @param $caseData
     * @param $order
     * @return \Signifyd\Core\Response\SaleResponse|bool
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     * @throws \Signifyd\Core\Exceptions\CaseModelException
     * @throws \Signifyd\Core\Exceptions\InvalidClassException
     * @throws \Signifyd\Core\Exceptions\LoggerException
     */
    public function postCaseToSignifyd($caseData, $order)
    {
        /** @var \Signifyd\Core\Response\SaleResponse $saleResponse */
        $saleResponse = $this->configHelper->getSignifydSaleApi($order)->createOrder('orders/events/sales', $caseData);

        if (empty($saleResponse->getSignifydId()) === false) {
            $this->logger->debug("Case sent. Id is {$saleResponse->getSignifydId()}", ['entity' => $order]);
            $this->orderHelper->addCommentToStatusHistory(
                $order,
                "Signifyd: case created {$saleResponse->getSignifydId()}"
            );
            return $saleResponse;
        } else {
            $this->logger->error($this->jsonSerializer->serialize($saleResponse));
            $this->logger->error("Case failed to send.", ['entity' => $order]);
            $this->orderHelper->addCommentToStatusHistory($order, "Signifyd: failed to create case");

            return false;
        }
    }

    public function createReroute($updateData, $order)
    {
        $caseResponse = $this->configHelper->getSignifydSaleApi($order)->reroute($updateData);

        if (empty($caseResponse->getSignifydId()) === false) {
            $this->logger->debug("Reroute created. Id is {$caseResponse->getSignifydId()}", ['entity' => $order]);
            return $caseResponse;
        } else {
            $this->logger->error($this->jsonSerializer->serialize($caseResponse));
            $this->logger->error("Reroute failed to create.", ['entity' => $order]);
            return false;
        }
    }

    /**
     * @param Order $order
     * @return bool
     */
    public function cancelCaseOnSignifyd(Order $order)
    {
        $this->logger->debug("Trying to cancel case for order " . $order->getIncrementId(), ['entity' => $order]);

        /** @var $case \Signifyd\Connect\Model\Casedata */
        $case = $this->casedataFactory->create();
        $this->casedataResourceModel->load($case, $order->getId(), 'order_id');

        if ($case->isEmpty() || empty($case->getCode())) {
            $this->logger->debug(
                'Guarantee cancel skipped: case not found for order ' . $order->getIncrementId(),
                ['entity' => $order]
            );
            return false;
        }

        $guarantee = $case->getData('guarantee');

        if (empty($guarantee) || in_array($guarantee, ['DECLINED', 'REJECT', 'N/A'])) {
            $this->logger->debug("Guarantee cancel skipped: current guarantee is {$guarantee}", ['entity' => $order]);
            return false;
        }

        /** @var \Magento\Sales\Model\Order\Item $item */
        foreach ($order->getAllItems() as $item) {
            if ($item->getQtyToCancel() > 0 || $item->getQtyToRefund() > 0) {
                $message = 'Guarantee cancel skipped: order still have items not canceled or refunded';
                $this->logger->debug($message, ['entity' => $order]);
                return false;
            }
        }

        $this->logger->debug('Cancelling case ' . $case->getData('order_id'), ['entity' => $order]);
        $signifydGuarantee = $this->guaranteeModelFactory->create();
        $signifydGuarantee->setCaseId($case->getCode());
        $guaranteeResponse = $this->configHelper->getSignifydGuaranteeApi($order)->cancelGuarantee($signifydGuarantee);
        $disposition = $guaranteeResponse->getDisposition();

        $this->logger->debug("Cancel disposition result {$disposition}", ['entity' => $order]);

        if ($disposition == 'CANCELED') {
            try {
                $this->orderHelper->addCommentToStatusHistory($order, "Signifyd: guarantee canceled");
                $order->setSignifydGuarantee($disposition);
                $this->orderResourceModel->save($case->getOrder());
                $isCaseLocked = $this->casedataResourceModel->isCaseLocked($case);

                // Some other process already locked the case, will not load or save
                if ($isCaseLocked === false) {
                    $this->casedataResourceModel->loadForUpdate($case, $case->getId(), null, 2);
                }

                $case->setData('guarantee', $disposition);

                // Some other process already locked the case, will not load or save
                if ($isCaseLocked === false) {
                    $this->casedataResourceModel->save($case);
                }
            } catch (\Exception $e) {
                // Triggering case save to unlock case
                if ($case instanceof \Signifyd\Connect\Model\Casedata) {
                    $this->casedataResourceModel->save($case);
                }

                $this->logger->error('Failed to save case data to database: ' . $e->getMessage());
            }

            return true;
        } else {
            $this->orderHelper->addCommentToStatusHistory($order, "Signifyd: failed to cancel guarantee");

            return false;
        }
    }

    /**
     * Gets AVS code for order payment method.
     *
     * @param Order $order
     * @return string
     */
    public function getAvsCode(Order $order)
    {
        try {
            $avsAdapter = $this->paymentVerificationFactory->createPaymentAvs($order->getPayment()->getMethod());

            $this->logger->debug('Getting AVS code using ' . get_class($avsAdapter), ['entity' => $order]);

            $avsCode = $avsAdapter->getData($order);

            if (isset($avsCode) === false) {
                return null;
            }

            $avsCode = trim(strtoupper($avsCode));

            if ($avsAdapter->validate($avsCode)) {
                return $avsCode;
            } else {
                return null;
            }
        } catch (Exception $e) {
            $this->logger->error('Error fetching AVS code: ' . $e->getMessage(), ['entity' => $order]);
            return '';
        }
    }

    /**
     * Gets CVV code for order payment method.
     *
     * @param Order $order
     * @return string
     */
    public function getCvvCode(Order $order)
    {
        try {
            $cvvAdapter = $this->paymentVerificationFactory->createPaymentCvv($order->getPayment()->getMethod());

            $this->logger->debug('Getting CVV code using ' . get_class($cvvAdapter), ['entity' => $order]);

            $cvvCode = $cvvAdapter->getData($order);

            if (isset($cvvCode) === false) {
                return null;
            }

            $cvvCode = trim(strtoupper($cvvCode));

            if ($cvvAdapter->validate($cvvCode)) {
                return $cvvCode;
            } else {
                return null;
            }
        } catch (Exception $e) {
            $this->logger->error('Error fetching CVV code: ' . $e->getMessage(), ['entity' => $order]);
            return null;
        }
    }

    /**
     * Gets cardholder for order
     *
     * @param Order $order
     * @return string
     */
    public function getCardholder(Order $order)
    {
        try {
            $paymentMethod = $order->getPayment()->getMethod();
            $cardholderAdapter = $this->paymentVerificationFactory->createPaymentCardholder($paymentMethod);

            $this->logger->debug('Getting card holder using ' . get_class($cardholderAdapter), ['entity' => $order]);

            $cardholder = $cardholderAdapter->getData($order);

            if (empty($cardholder) || !mb_check_encoding($cardholder, 'UTF-8') || strpos($cardholder, '?') !== false) {
                $firstname = $order->getBillingAddress()->getFirstname();
                $lastname = $order->getBillingAddress()->getLastname();
                $cardholder = trim($firstname) . ' ' . trim($lastname);
            }

            $cardholder = strtoupper($cardholder);

            $cardholder = preg_replace('/  +/', ' ', $cardholder);

            return $cardholder;
        } catch (Exception $e) {
            $this->logger->error('Error fetching cardholder: ' . $e->getMessage(), ['entity' => $order]);
            return '';
        }
    }

    /**
     * Gets last4 for order payment method.
     *
     * @param Order $order
     * @return string|null
     */
    public function getLast4(Order $order)
    {
        try {
            $last4Adapter = $this->paymentVerificationFactory->createPaymentLast4($order->getPayment()->getMethod());

            $this->logger->debug('Getting last4 using ' . get_class($last4Adapter), ['entity' => $order]);

            $last4 = $last4Adapter->getData($order);

            if (isset($last4) === false) {
                return null;
            }

            $last4 = preg_replace('/\D/', '', $last4);

            if (!empty($last4) && strlen($last4) == 4 && is_numeric($last4)) {
                return (string) $last4;
            }

            return null;
        } catch (Exception $e) {
            $this->logger->error('Error fetching last4: ' . $e->getMessage(), ['entity' => $order]);
            return null;
        }
    }

    /**
     * Gets expiration month for order payment method.
     *
     * @param Order $order
     * @return int|null
     */
    public function getExpMonth(Order $order)
    {
        try {
            $monthAdapter = $this->paymentVerificationFactory->createPaymentExpMonth($order->getPayment()->getMethod());

            $this->logger->debug('Getting expiry month using ' . get_class($monthAdapter), ['entity' => $order]);

            $expMonth = $monthAdapter->getData($order);

            if (isset($expMonth) === false) {
                return null;
            }

            $expMonth = preg_replace('/\D/', '', $expMonth);

            $expMonth = (int) $expMonth;
            if ($expMonth < 1 || $expMonth > 12) {
                return null;
            }

            return $expMonth;
        } catch (Exception $e) {
            $this->logger->error('Error fetching expiration month: ' . $e->getMessage(), ['entity' => $order]);
            return null;
        }
    }

    /**
     * Gets expiration year for order payment method.
     *
     * @param Order $order
     * @return int|null
     */
    public function getExpYear(Order $order)
    {
        try {
            $yearAdapter = $this->paymentVerificationFactory->createPaymentExpYear($order->getPayment()->getMethod());

            $this->logger->debug('Getting expiry year using ' . get_class($yearAdapter), ['entity' => $order]);

            $expYear = $yearAdapter->getData($order);

            if (isset($expYear) === false) {
                return null;
            }

            $expYear = preg_replace('/\D/', '', $expYear);

            $expYear = (int) $expYear;
            if ($expYear <= 0) {
                return null;
            }

            //If returned expiry year has less then 4 digits
            if ($expYear < 1000) {
                $expYear += 2000;
            }

            return $expYear;
        } catch (Exception $e) {
            $this->logger->error('Error fetching expiration year: ' . $e->getMessage(), ['entity' => $order]);
            return null;
        }
    }

    /**
     * Gets credit card bin for order payment method.
     *
     * @param Order $order
     * @return int|null
     */
    public function getBin(Order $order)
    {
        try {
            $binAdapter = $this->paymentVerificationFactory->createPaymentBin($order->getPayment()->getMethod());

            $this->logger->debug('Getting bin using ' . get_class($binAdapter), ['entity' => $order]);

            $bin = $binAdapter->getData($order);

            if (isset($bin) === false) {
                return null;
            }

            $bin = preg_replace('/\D/', '', $bin);

            if (empty($bin)) {
                return null;
            }

            $bin = (int) $bin;
            // A credit card does not starts with zero, so the bin intaval has to be at least 100.000
            if ($bin < 100000) {
                return null;
            }

            return (string) $bin;
        } catch (Exception $e) {
            $this->logger->error('Error fetching bin: ' . $e->getMessage(), ['entity' => $order]);
            return null;
        }
    }

    /**
     * Gets transaction ID for order payment method.
     *
     * @param Order $order
     * @return int|null
     */
    public function getTransactionId(Order $order)
    {
        try {
            $paymentMethod = $order->getPayment()->getMethod();
            $transactionIdAdapter = $this->paymentVerificationFactory->createPaymentTransactionId($paymentMethod);

            $message = 'Getting transaction ID using ' . get_class($transactionIdAdapter);
            $this->logger->debug($message, ['entity' => $order]);

            $transactionId = $transactionIdAdapter->getData($order);

            if (empty($transactionId)) {
                return null;
            }

            return $transactionId;
        } catch (Exception $e) {
            $this->logger->error('Error fetching transaction ID: ' . $e->getMessage(), ['entity' => $order]);
            return null;
        }
    }

    /**
     * Construct a new case object from quote
     * @param $quote Order
     * @return array
     */
    public function processQuoteData(Quote $quote, $checkoutPaymentDetails = null, $paymentMethod = null)
    {
        $case = [];
        $reservedOrderId = $quote->getReservedOrderId();

        if (empty($reservedOrderId)) {
            $quote->reserveOrderId();
            $reservedOrderId = $quote->getReservedOrderId();
            $this->quoteResourceModel->save($quote);
        }

        $case['orderId'] = $reservedOrderId;
        $case['purchase'] = $this->makePurchaseFromQuote($quote);
        $case['userAccount'] = $this->makeUserAccountFromQuote($quote);
        $case['memberships'] = $this->makeMemberships();
        $case['coverageRequests'] = $this->getDecisionRequest($paymentMethod);
        $case['merchantCategoryCode'] = $this->makeMerchantCategoryCode();
        $case['device'] = $this->makeDevice($quote->getId(), $quote->getStore());
        $case['merchantPlatform'] = $this->getMerchantPlataform();
        $case['signifydClient'] = $this->makeVersions();
        $case['sellers'] = $this->getSellers();
        $case['tags'] = $this->getTags($quote->getStoreId());
        $case['customerOrderRecommendation'] = $this->getCustomerOrderRecommendation();

        $policyConfig = $this->getPolicyName(
            $quote->getStore()->getScopeType(),
            $quote->getStoreId()
        );
        $policyFromMethod = $this->getPolicyFromMethod($policyConfig, $paymentMethod);
        $evalRequest = ($policyFromMethod == 'TRA_PRE_AUTH') ? ['SCA_EVALUATION'] : null;
        $case['additionalEvalRequests'] = $evalRequest;

        $case['checkoutId'] = sha1($this->jsonSerializer->serialize($case));

        $transactions = [];

        if (isset($paymentMethod)) {
            $transaction = [];
            $billingAddres = $quote->getBillingAddress();
            $transaction['checkoutPaymentDetails']['billingAddress'] =
                $this->formatSignifydAddress($billingAddres);
            $transaction['currency'] = $quote->getBaseCurrencyCode();
            $transaction['amount'] = $quote->getGrandTotal();
            $transaction['sourceAccountDetails'] = $this->makeSourceAccountDetails();
            $transaction['acquirerDetails'] = $this->makeAcquirerDetails();
            $transaction['paymentMethod'] = $this->makePaymentMethod($paymentMethod);
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

        $case['transactions'] = $transactions;

        /**
         * This registry entry it's used to collect data from some payment methods like Payflow Link
         * It must be unregistered after use
         * @see \Signifyd\Connect\Plugin\Magento\Paypal\Model\Payflowlink
         */
        $this->registry->unregister('signifyd_payment_data');

        return $case;
    }

    public function getPolicyName($scopeType = ScopeConfigInterface::SCOPE_TYPE_DEFAULT, $scopeCode = null)
    {
        return $this->scopeConfigInterface->getValue(
            'signifyd/advanced/policy_name',
            $scopeType,
            $scopeCode
        );
    }

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
                $purchase['products'][] = $this->makeProductFromQuote($item);
            }
        }

        $shippingAmount = $quote->getShippingAddress()->getShippingAmount();
        $purchase['shipments'] = $this->makeShipmentsFromQuote($quote);
        $purchase['confirmationPhone'] = $quote->getCustomerEmail();
        $purchase['totalShippingCost'] = is_numeric($shippingAmount) ? floatval($shippingAmount) : null;
        $purchase['discountCodes'] = null;
        $purchase['receivedBy'] = $this->getReceivedBy();

        return $purchase;
    }

    /**
     * @param \Magento\Quote\Model\Quote\Item $item
     * @return array
     */
    public function makeProductFromQuote(\Magento\Quote\Model\Quote\Item $item)
    {
        $product = $item->getProduct();
        $productImageUrl = $this->getProductImage($product);

        $productCategorysId = $product->getCategoryIds();
        $categoryCollection = $this->categoryCollectionFactory->create()
            ->addFieldToFilter('entity_id', ['in' => $productCategorysId])
            ->addFieldToFilter('level', ['neq' => 0])
            ->setOrder('position', 'ASC')
            ->setOrder('level', 'ASC');
        $productCategoryId = null;
        $productSubCategoryId = null;

        /** @var \Magento\Catalog\Model\Category $category */
        foreach ($categoryCollection as $category) {
            if (isset($productCategoryId) && isset($productSubCategoryId)) {
                break;
            }

            switch ($category->getLevel()) {
                case 2:
                    $productCategoryId = $category->getId();
                    break;
                case 3:
                    $productSubCategoryId = $category->getId();
                    break;
            }
        }

        if (isset($productCategoryId)) {
            /** @var \Magento\Catalog\Model\Category $mainCategory */
            $mainCategory = $this->categoryFactory->create();
            $this->categoryResourceModel->load($mainCategory, $productCategoryId);
            $mainCategoryName = $mainCategory->getName();
        } else {
            $mainCategoryName = null;
        }

        if (isset($productSubCategoryId)) {
            /** @var \Magento\Catalog\Model\Category $subCategory */
            $subCategory = $this->categoryFactory->create();
            $this->categoryResourceModel->load($subCategory, $productSubCategoryId);
            $subCategoryName = $subCategory->getName();
        } else {
            $subCategoryName = null;
        }

        $itemPrice = floatval(number_format($item->getPriceInclTax(), 2, '.', ''));

        if ($itemPrice <= 0) {
            if ($item->getParentItem()) {
                if ($item->getParentItem()->getProductType() === 'configurable') {
                    $itemPrice = floatval(number_format($item->getParentItem()->getPriceInclTax(), 2, '.', ''));
                }
            }
        }

        $product = [];
        $product['itemName'] = $item->getName();
        $product['itemPrice'] = $itemPrice;
        $product['itemQuantity'] = (int)$item->getQty();
        $product['itemIsDigital'] = (bool) $item->getIsVirtual();
        $product['itemCategory'] = $mainCategoryName;
        $product['itemSubCategory'] = $subCategoryName;
        $product['itemId'] = $item->getSku();
        $product['itemImage'] = $productImageUrl;
        $product['itemUrl'] = $item->getProduct()->getProductUrl();
        $product['itemWeight'] = $item->getProduct()->getWeight();
        $product['shipmentId'] = null;
        $product['subscription'] = $this->makeSubscription();

        return $product;
    }

    /**
     * @param $quote Quote
     * @return array
     */
    public function makeRecipientFromQuote(Quote $quote)
    {
        $recipient = [];
        $address = $quote->getShippingAddress()->getCity() !== null ?
            $quote->getShippingAddress() : $quote->getBillingAddress();

        if ($address !== null) {
            $recipient['fullName'] = $address->getName();
            $recipient['organization'] = $address->getCompany();
            $recipient['address'] = $this->formatSignifydAddress($address);
        } else {
            $recipient['email'] = $quote->getCustomerEmail();
        }

        if (empty($recipient['fullName'])) {
            $recipient['fullName'] = $quote->getCustomerFirstname() . ' ' . $quote->getCustomerLastname();
        }

        return $recipient;
    }

    /** Construct a user account blob
     * @param $quote Quote
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

    /**
     * @param $caseData
     * @param $quote
     * @return bool|\Signifyd\Core\Response\CheckoutsResponse
     * @throws \Signifyd\Core\Exceptions\ApiException
     * @throws \Signifyd\Core\Exceptions\InvalidClassException
     * @throws \Signifyd\Core\Exceptions\LoggerException]
     */
    public function postCaseFromQuoteToSignifyd($caseData, $quote)
    {
        $caseResponse = $this->configHelper->getSignifydCheckoutApi($quote)
            ->createOrder('orders/events/checkouts', $caseData);

        if (empty($caseResponse->getSignifydId()) === false) {
            $this->logger->debug("Case sent. Id is {$caseResponse->getSignifydId()}", ['entity' => $quote]);
            return $caseResponse;
        } else {
            $this->logger->error($this->jsonSerializer->serialize($caseResponse));
            $this->logger->error("Case failed to send.", ['entity' => $quote]);

            return false;
        }
    }

    public function postTransactionToSignifyd($transactionData, $entity)
    {
        $caseResponse = $this->configHelper->getSignifydCheckoutApi($entity)->createTransaction($transactionData);
        $tokenSent = $transactionData['checkoutId'];
        $tokenReceived = $caseResponse->getCheckoutId();

        if ($tokenSent === $tokenReceived) {
            $message = $entity instanceof \Magento\Quote\Model\Quote ?
                "Transaction sent to quote {$entity->getId()}. Token is {$caseResponse->getCheckoutId()}" :
                "Transaction sent to order {$entity->getIncrementId()}. Token is {$caseResponse->getCheckoutId()}";

            $this->logger->debug($message);
            return $caseResponse;
        } else {
            $this->logger->error($this->jsonSerializer->serialize($caseResponse));
            $this->logger->error(
                "Transaction failed to send. Sent token ({$tokenSent}) is different from received ({$tokenReceived})"
            );
            return false;
        }
    }

    public function getIsPreAuth($policyName, $paymentMethod)
    {
        $policyFromMethod = $this->getPolicyFromMethod($policyName, $paymentMethod);

        return ($policyFromMethod == 'PRE_AUTH' || $policyFromMethod == 'TRA_PRE_AUTH');
    }

    public function getPolicyFromMethod($policyName, $paymentMethod)
    {
        if (isset($paymentMethod) === false) {
            return 'POST_AUTH';
        }

        if ($this->configHelper->isPaymentRestricted($paymentMethod)) {
            return 'POST_AUTH';
        }

        $isJson = $this->isJson($policyName);

        if ($isJson) {
            $configPolicy = $this->jsonSerializer->unserialize($policyName);

            foreach ($configPolicy as $key => $value) {
                if ($key == 'PRE_AUTH' || $key == 'TRA_PRE_AUTH') {
                    if (is_array($value) === false) {
                        continue;
                    }

                    if (in_array($paymentMethod, $value)) {
                        return $key;
                    }
                }
            }

            return 'POST_AUTH';
        } else {
            return $policyName;
        }
    }

    public function getDecisionForMethod($decision, $paymentMethod)
    {
        $isJson = $this->isJson($decision);

        if ($isJson) {
            if (isset($paymentMethod) === false) {
                return null;
            }

            $configDecisions = $this->jsonSerializer->unserialize($decision);

            foreach ($configDecisions as $configDecision => $method) {
                if ($this->isDecisionValid($configDecision) === false) {
                    continue;
                }

                if (in_array($paymentMethod, $method)) {
                    return [$configDecision];
                }
            }

            return null;
        } else {
            if ($this->isDecisionValid($decision) === false) {
                return null;
            }

            return [$decision];
        }
    }

    public function isDecisionValid($decision)
    {
        $allowedDecisions = ['FRAUD', 'INR', 'SNAD', 'ALL', 'NONE'];

        return in_array($decision, $allowedDecisions);
    }

    public function isJson($string)
    {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }

    public function getIsPreAuthInUse($policyName)
    {
        $isJson = $this->isJson($policyName);

        if ($isJson) {
            $configPolicy = $this->jsonSerializer->unserialize($policyName);

            if (isset($configPolicy['PRE_AUTH']) === false || is_array($configPolicy['PRE_AUTH']) === false) {
                return false;
            }

            return true;
        } else {
            return ($policyName == 'PRE_AUTH');
        }
    }

    public function getPastTransactionsYear($customerId)
    {
        $transactionsCount = 0;

        /** @var \Magento\Framework\Stdlib\DateTime\DateTime $date */
        $date = $this->dateTimeFactory->create();
        $gmtDate = $date->date(null, '-1 year');

        /** @var $orderCollection \Magento\Sales\Model\ResourceModel\Order\Collection */
        $orderCollection = $this->orderCollectionFactory->create();
        $orderCollection->addFieldToFilter('customer_id', ['eq' => $customerId]);
        $orderCollection->addFieldToFilter('created_at', ['gteq' => $gmtDate]);
        $orderCollection->addFieldToFilter('state', ['nin' => ['closed', 'canceled']]);
        $orderItems = $orderCollection->getItems();

        /** @var $o \Magento\Sales\Model\Order */
        foreach ($orderItems as $o) {
            $transactions = $this->transactions->create()->addOrderIdFilter($o->getId());
            $transactionsCount += $transactions->getTotalCount();
        }

        return $transactionsCount;
    }

    public function getPurchasesLast6Months($customerId)
    {
        /** @var \Magento\Framework\Stdlib\DateTime\DateTime $date */
        $date = $this->dateTimeFactory->create();
        $gmtDate = $date->date(null, '-6 months');

        /** @var $orderCollection \Magento\Sales\Model\ResourceModel\Order\Collection */
        $orderCollection = $this->orderCollectionFactory->create();
        $orderCollection->addFieldToFilter('customer_id', ['eq' => $customerId]);
        $orderCollection->addFieldToFilter('state', ['eq' => 'complete']);
        $orderCollection->addFieldToFilter('created_at', ['gteq' => $gmtDate]);

        return $orderCollection->getTotalCount();
    }

    public function makeDevice($quoteId, $storeId)
    {
        $filterIpd = $this->filterIp($this->remoteAddress->getRemoteAddress());

        if (isset($filterIpd) === false) {
            return null;
        }

        $device = [];
        $device['clientIpAddress'] = $filterIpd;
        $device['sessionId'] = $this->deviceHelper->generateFingerprint($quoteId, $storeId);
        $device['fingerprint'] = $this->getDeviceFingerprints();
        return $device;
    }

    public function getMerchantPlataform()
    {
        $merchantPlataform = [];
        $merchantPlataform['name'] = 'Magento';
        $merchantPlataform['version'] = $this->productMetadataInterface->getVersion();
        return $merchantPlataform;
    }

    /**
     * makeSourceAccountDetails method should be extended/intercepted by plugin to add value to it.
     * These are details about the Payment Instrument
     * that are sourced directly from the institution that manages that instrument, the issuing bank for example.
     * This method should be extended/intercepted by plugin to add value to it
     *
     * @return null
     */
    public function makeSourceAccountDetails()
    {
        return null;
    }

    /**
     * makeAcquirerDetails method should be extended/intercepted by plugin to add value to it.
     * Details about the merchant's acquiring bank.
     * Although this information is optional, if it is not present it may result in missed SCA exemptions/exclusions.
     * This method should be extended/intercepted by plugin to add value to it
     *
     * @return null
     */
    public function makeAcquirerDetails()
    {
        return null;
    }

    /**
     * makeScaExemptionRequested method should be extended/intercepted by plugin to add value to it.
     * The SCA exemption that was requested by the merchant
     * This method should be extended/intercepted by plugin to add value to it
     *
     * @return null
     */
    public function makeScaExemptionRequested()
    {
        return null;
    }

    /**
     * makeThreeDsResult method should be extended/intercepted by plugin to add value to it.
     * These are details about the result of the 3D Secure authentication
     * This method should be extended/intercepted by plugin to add value to it
     *
     * @return null
     */
    public function makeThreeDsResult()
    {
        return null;
    }

    /**
     * If this product is being delivered as part of a subscription, then you can include these fields
     * to include data about the subscription itself. The entire itemQuantity on this
     * product should be purchased via the subscription.
     * If the buyer added extra items of the same product, those should be listed in separate product line item.
     * This method should be extended/intercepted by plugin to add value to it
     *
     * @return null
     */
    public function makeSubscription()
    {
        return null;
    }

    /**
     * The membership object should be used to indicate the usage of a rewards, discount,
     * or admission program by the buyer when they completed the checkout.
     * This method should be extended/intercepted by plugin to add value to it
     *
     * @return null
     */
    public function makeMemberships()
    {
        return null;
    }

    /**
     * A Merchant Category Code (MCC) is a four-digit number listed in ISO 18245 for retail financial services.
     * This method should be extended/intercepted by plugin to add value to it
     *
     * @return null
     */
    public function makeMerchantCategoryCode()
    {
        return null;
    }

    /**
     * The routing number (ABA) of the bank account that was used as provided during checkout.
     * This method should be extended/intercepted by plugin to add value to it
     *
     * @return null
     */
    public function makeAbaRoutingNumber()
    {
        return null;
    }

    /**
     * A unique string value as provided by the cardTokenProvider which replaces the card Primary Account Number (PAN).
     * The same cardToken from the same cardTokenProvider should never be from two different PANs.
     * This method should be extended/intercepted by plugin to add value to it
     *
     * @return null
     */
    public function makeCardToken()
    {
        return null;
    }

    /**
     * The issuer of the cardToken, that is, whomever generated the cardToken originally.
     * This method should be extended/intercepted by plugin to add value to it
     *
     * @return null
     */
    public function makeCardTokenProvider()
    {
        return null;
    }

    /**
     * Details about the installment plan used to make the purchase.
     * This method should be extended/intercepted by plugin to add value to it
     *
     * @return null
     */
    public function makeCardInstallments()
    {
        return null;
    }

    /**
     * This method should be extended/intercepted by plugin to add value to it
     *
     * @return null
     */
    public function makeMinDeliveryDate()
    {
        return null;
    }

    /**
     * This method should be extended/intercepted by plugin to add value to it
     *
     * @return null
     */
    public function makeFulfillmentMethod()
    {
        return null;
    }

    /**
     * This method should be extended/intercepted by plugin to add value to it
     *
     * @return null
     */
    public function makeAccountLast4()
    {
        return null;
    }

    /**
     * This method should be extended/intercepted by plugin to add value to it
     *
     * @return null
     */
    public function makeCardBrand()
    {
        return null;
    }
}
