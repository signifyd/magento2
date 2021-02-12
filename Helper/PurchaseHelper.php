<?php
/**
 * Copyright 2015 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Signifyd\Connect\Helper;

use Braintree\Exception;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address;
use Magento\Sales\Model\Order\Item;
use Magento\Sales\Model\ResourceModel\Order as OrderResourceModel;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Framework\App\ProductMetadata;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\ResourceModel\Customer as CustomerResourceModel;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
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
        StoreManagerInterface $storeManagerInterface
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
        $productImage = $product->getImage();

        if (isset($productImage)) {
            $productImageUrl = $this->storeManagerInterface->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA) . 'catalog/product' . $productImage;
        } else {
            $productImageUrl = null;
        }

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
        $product['itemId'] = $item->getSku();
        $product['itemName'] = $item->getName();
        $product['itemIsDigital'] = (bool) $item->getIsVirtual();
        $product['itemPrice'] = $itemPrice;
        $product['itemQuantity'] = (int)$item->getQtyOrdered();
        $product['itemUrl'] = $item->getProduct()->getProductUrl();
        $product['itemWeight'] = $item->getProduct()->getWeight();
        $product['itemImage'] = $productImageUrl;
        $product['itemCategory'] = $mainCategoryName;
        $product['itemSubCategory'] = $subCategoryName;

        return $product;
    }

    /**
     * @param $order Order
     * @return array
     */
    public function makePurchase(Order $order)
    {
        $originStoreCode = $order->getData('origin_store_code');

        // Get all of the purchased products
        $items = $order->getAllItems();
        $purchase = [];

        if ($originStoreCode == 'admin') {
            $purchase['orderChannel'] = "PHONE";
        } elseif (!empty($originStoreCode)) {
            $purchase['orderChannel'] = "WEB";
        }

        $purchase['products'] = [];

        /** @var \Magento\Sales\Model\Order\Item $item */
        foreach ($items as $item) {
            $children = $item->getChildrenItems();

            if (is_array($children) == false || empty($children)) {
                $purchase['products'][] = $this->makeProduct($item);
            }
        }

        $purchase['totalPrice'] = $order->getGrandTotal();
        $purchase['currency'] = $order->getOrderCurrencyCode();
        $purchase['orderId'] = $order->getIncrementId();
        $purchase['createdAt'] = date('c', strtotime($order->getCreatedAt()));
        $purchase['browserIpAddress'] = $this->getIPAddress($order);

        $couponCode = $order->getCouponCode();

        if (empty($couponCode) === false) {
            $purchase['discountCodes'] = [
                'amount' => abs($order->getDiscountAmount()),
                'code' => $couponCode
            ];
        }

        $purchase['shipments'] = $this->makeShipments($order);

        if (empty($originStoreCode) === false &&
            $originStoreCode != 'admin' &&
            $this->deviceHelper->isDeviceFingerprintEnabled()
        ) {
            $purchase['orderSessionId']= $this->deviceHelper->generateFingerprint($order->getQuoteId());
        }

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

        if (empty($shippingMethod) === false) {
            $shipment = [];
            $shipment['shipper'] = $this->makeShipper($shippingMethod);
            $shipment['shippingPrice'] = floatval($order->getShippingAmount()) + floatval($order->getShippingTaxAmount());
            $shipment['shippingMethod'] = $this->makeshippingMethod($shippingMethod);

            $shipments[] = $shipment;
        }

        return $shipments;
    }

    public function makeShipper($shippingMethod)
    {
        $shippingCarrier = $shippingMethod->getCarrierCode();
        $allowMethodsJson = $this->scopeConfigInterface->getValue('signifyd/general/shipper_config');
        $allowMethods = $this->jsonSerializer->unserialize($allowMethodsJson);

        foreach ($allowMethods as $i => $allowMethod) {
            if (in_array($shippingCarrier, $allowMethod)) {
                return $i;
            }
        }

        return false;
    }

    public function makeshippingMethod($shippingMethod)
    {
        $shippingMethodCode = $shippingMethod->getMethod();
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
     * @param $mageAddress Address
     * @return array
     */
    public function formatSignifydAddress($mageAddress)
    {
        $address = [];

        $address['streetAddress'] = $mageAddress->getStreetLine(1);
        $address['unit'] = $mageAddress->getStreetLine(2);
        $address['city'] = $mageAddress->getCity();
        $address['provinceCode'] = $mageAddress->getRegionCode();
        $address['postalCode'] = $mageAddress->getPostcode();
        $address['countryCode'] = $mageAddress->getCountryId();

        return $address;
    }

    /**
     * @param $order Order
     * @return array
     */
    public function makeRecipient(Order $order)
    {
        $recipients = [];
        $recipient = [];
        $address = $order->getShippingAddress();

        if ($address !== null) {
            $recipient['fullName'] = $address->getName();
            $recipient['confirmationEmail'] = $address->getEmail();
            $recipient['confirmationPhone'] = $address->getTelephone();
            $recipient['organization'] = $address->getCompany();
            $recipient['deliveryAddress'] = $this->formatSignifydAddress($address);
        }

        if (empty($recipient->fullName)) {
            $recipient['fullName'] = $order->getCustomerName();
        }

        if (empty($recipient['confirmationEmail'])) {
            $recipient['confirmationEmail'] = $order->getCustomerEmail();
        }

        $recipients[] = $recipient;

        return $recipients;
    }

    public function makeTransactions(Order $order)
    {
        $lastTransaction = $order->getPayment()->getLastTransId();
        $transactionsFromOrder = $this->transactionCollectionFactory->create()
            ->addFieldToFilter('txn_id', ['eq' => $lastTransaction]);
        $transactionFromOrder = $transactionsFromOrder->getFirstItem();
        $transactionType = $transactionFromOrder->getData('txn_type');

        if ($transactionType == 'authorization') {
            $transactionType = 'AUTHORIZATION';
        } elseif ($transactionType == 'capture') {
            $transactionType = 'SALE';
        } else {
            $transactionType = 'PREAUTHORIZATION';
        }

        $transactions = [];
        $lastTransaction = [];

        $lastTransaction['checkoutPaymentDetails'] = $this->makecheckoutPaymentDetails($order);
        $lastTransaction['avsResponseCode'] = $this->getAvsCode($order);
        $lastTransaction['cvvResponseCode'] = $this->getCvvCode($order);
        $lastTransaction['transactionId'] = $this->getTransactionId($order);
        $lastTransaction['currency'] = $order->getOrderCurrencyCode();
        $lastTransaction['amount'] = $order->getGrandTotal();
        $lastTransaction['gateway'] = $order->getPayment()->getMethod();
        $lastTransaction['createdAt'] = date('c', strtotime($transactionFromOrder->getData('created_at')));
        $lastTransaction['paymentMethod'] = $this->makePaymentMethod($order);
        $lastTransaction['type'] = $transactionType;
        $lastTransaction['gatewayStatusCode'] = 'SUCCESS';

        $transactions[] = $lastTransaction;

        return $transactions;
    }

    public function makePaymentMethod(Order $order)
    {
        $paymentMethod = $order->getPayment()->getMethod();
        $allowMethodsJson = $this->scopeConfigInterface->getValue('signifyd/general/payment_methods_config');
        $allowMethods = $this->jsonSerializer->unserialize($allowMethodsJson);

        foreach ($allowMethods as $i => $allowMethod) {
            if (in_array($paymentMethod, $allowMethod)) {
                return $i;
            }
        }

        return false;
    }

    /**
     * @param $order Order
     * @return array
     */
    public function makecheckoutPaymentDetails(Order $order)
    {
        $billingAddress = $order->getBillingAddress();
        $checkoutPaymentDetails = [];
        $checkoutPaymentDetails['holderName'] = $this->getCardholder($order);
        $checkoutPaymentDetails['cardBin'] = $this->getBin($order);
        $checkoutPaymentDetails['cardLast4'] = $this->getLast4($order);
        $checkoutPaymentDetails['cardExpiryMonth'] = $this->getExpMonth($order);
        $checkoutPaymentDetails['cardExpiryYear'] = $this->getExpYear($order);
        $checkoutPaymentDetails['billingAddress'] = $this->formatSignifydAddress($billingAddress);

        return $checkoutPaymentDetails;
    }

    /** Construct a user account blob
     * @param $order Order
     * @return array
     */
    public function makeUserAccount(Order $order)
    {
        $user = [];
        $user['email'] = $order->getCustomerEmail();
        $user['username'] = $order->getCustomerEmail();
        $user['accountNumber'] = $order->getCustomerId();
        $user['phone'] = $order->getBillingAddress()->getTelephone();
        $user['aggregateOrderCount'] = 0;
        $user['aggregateOrderDollars'] = 0.0;

        /* @var $customer \Magento\Customer\Model\Customer */
        $customer = $this->customerFactory->create();
        $this->customerResourceModel->load($customer, $order->getCustomerId());

        if ($customer !== null && !$customer->isEmpty()) {
            $user['createdDate'] = date('c', strtotime($customer->getCreatedAt()));
            $user['lastUpdateDate'] = date('c', strtotime($customer->getData('updated_at')));

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

        $case['purchase'] = $this->makePurchase($order);
        $case['recipients'] = $this->makeRecipient($order);
        $case['transactions'] = $this->makeTransactions($order);
        $case['userAccount'] = $this->makeUserAccount($order);

        /**
         * This registry entry it's used to collect data from some payment methods like Payflow Link
         * It must be unregistered after use
         * @see \Signifyd\Connect\Plugin\Magento\Paypal\Model\Payflowlink
         */
        $this->registry->unregister('signifyd_payment_data');

        return $case;
    }

    /**
     * @param $caseData
     * @param $order
     * @return \Signifyd\Core\Response\CaseResponse|bool
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     * @throws \Signifyd\Core\Exceptions\CaseModelException
     * @throws \Signifyd\Core\Exceptions\InvalidClassException
     * @throws \Signifyd\Core\Exceptions\LoggerException
     */
    public function postCaseToSignifyd($caseData, $order)
    {
        $caseResponse = $this->configHelper->getSignifydCaseApi($order)->createCase($caseData);

        if (empty($caseResponse->getCaseId()) === false) {
            $this->logger->debug("Case sent. Id is {$caseResponse->getCaseId()}", ['entity' => $order]);
            $this->orderHelper->addCommentToStatusHistory($order, "Signifyd: case created {$caseResponse->getCaseId()}");
            return $caseResponse;
        } else {
            $this->logger->error(serialize($caseResponse));
            $this->logger->error("Case failed to send.", ['entity' => $order]);
            $this->orderHelper->addCommentToStatusHistory($order, "Signifyd: failed to create case");

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

        if (empty($guarantee) || in_array($guarantee, ['DECLINED'])) {
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
        $signifydGuarantee = $this->guaranteeModelFactory->create([['caseId' => $case->getCode()]]);
        $guaranteeResponse = $this->configHelper->getSignifydGuaranteeApi($order)->cancelGuarantee($signifydGuarantee);
        $disposition = $guaranteeResponse->getDisposition();

        $this->logger->debug("Cancel disposition result {$disposition}", ['entity' => $order]);

        if ($disposition == 'CANCELED') {
            try {
                $this->orderHelper->addCommentToStatusHistory($order, "Signifyd: guarantee canceled");
                $order->setSignifydGuarantee($disposition);
                $this->orderResourceModel->save($case->getOrder());

                $this->casedataResourceModel->loadForUpdate($case, $case->getId(), null, 2);
                $case->setData('guarantee', $disposition);
                $this->casedataResourceModel->save($case);
            } catch (\Exception $e) {
                // Triggering case save to unlock case
                if ($case instanceof \Signifyd\Connect\Model\ResourceModel\Casedata) {
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
            $bin = preg_replace('/\D/', '', $bin);

            if (empty($bin)) {
                return null;
            }

            $bin = (int) $bin;
            // A credit card does not starts with zero, so the bin intaval has to be at least 100.000
            if ($bin < 100000) {
                return null;
            }

            return $bin;
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
}
