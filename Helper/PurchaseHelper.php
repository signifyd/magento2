<?php
/**
 * Copyright 2015 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Signifyd\Connect\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\AppInterface;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address;
use \Magento\Framework\ObjectManagerInterface;
use Magento\Sales\Model\Order\Item;
use Signifyd\Connect\Model\CaseRetry;
use Signifyd\Core\SignifydModel;
use Signifyd\Models\Address as SignifydAddress;
use Signifyd\Models\Card;
use Signifyd\Models\CaseModel;
use Signifyd\Models\Product;
use Signifyd\Models\Purchase;
use Signifyd\Models\Recipient;
use Signifyd\Models\UserAccount;
use Magento\Framework\Stdlib\DateTime\DateTime;

/**
 * Class PurchaseHelper
 * Handles the conversion from Magento Order to Signifyd Case and sends to Signifyd service.
 * @package Signifyd\Connect\Helper
 */
class PurchaseHelper
{
    /**
     * @var \Signifyd\Connect\Helper\LogHelper
     */
    protected $_logger;

    /**
     * @var \Signifyd\Core\SignifydAPI
     */
    protected $_api;

    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $_objectManager;

    /**
     * @var DateTime
     */
    protected $_dateTime;

    /**
     * @var Magento\Framework\Module\ModuleListInterface
     */
    protected $_moduleList;

    /**
     * @param ObjectManagerInterface $objectManager
     * @param LogHelper $logger
     * @param DateTime $dateTime
     * @param ScopeConfigInterface $scopeConfig
     * @param SignifydAPIMagento $api
     * @param ModuleListInterface $moduleList
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        LogHelper $logger,
        DateTime $dateTime,
        ScopeConfigInterface $scopeConfig,
        SignifydAPIMagento $api,
        ModuleListInterface $moduleList
    ) {
        $this->_logger = $logger;
        $this->_objectManager = $objectManager;
        $this->_moduleList = $moduleList;
        try {
            $this->_api = $api;

        } catch (\Exception $e) {
            $this->_logger->error($e);
        }

    }

    /**
     * Getting the ip address of the order
     * @param Order $order
     * @return mixed
     */
    protected function getIPAddress(Order $order)
    {
        if ($order->getRemoteIp()) {
            if ($order->getXForwardedFor()) {
                return $this->filterIp($order->getXForwardedFor());
            }

            return $this->filterIp($order->getRemoteIp());
        }

        /** @var $case \Magento\Framework\HTTP\PhpEnvironment\RemoteAddress */
        $remoteAddressHelper = $this->_objectManager->get('Magento\Framework\HTTP\PhpEnvironment\RemoteAddress');
        return $this->filterIp($remoteAddressHelper->getRemoteAddress());
    }

    /**
     * Filter the ip address
     * @param $ip
     * @return mixed
     */
    protected function filterIp($ip)
    {
        $matches = array();

        // Uses format IPv4
        if (preg_match('/[0-9]{1,3}(?:\.[0-9]{1,3}){3}/', $ip, $matches)) {
            return current($matches);
        }

        // Uses format IPv6
        if (preg_match('/[a-f0-9]{0,4}(?:\:[a-f0-9]{0,4}){2,7}/', strtolower($ip), $matches)) {
            return current($matches);
        }

        return preg_replace('/[^0-9a-zA-Z:\.]/', '', strtok(str_replace($ip, ',', "\n"), "\n"));
    }

    /**
     * Getting the version of Magento and the version of the extension
     * @return array
     */
    protected function getVersions()
    {
        $version = array();
        $version['platform'] = 'magento2';

        $productMetadata = $this->_objectManager->get('\Magento\Framework\App\ProductMetadata');
        $version['platformVersion'] = $productMetadata->getVersion();

        $version['pluginVersion'] = (string)($this->_moduleList->getOne('Signifyd_Connect')['setup_version']);
        return $version;
    }

    /**
     * @param Item $item
     * @return Product
     */
    protected function makeProduct(Item $item)
    {
        $product = SignifydModel::Make("\\Signifyd\\Models\\Product");
        $product->itemId = $item->getSku();
        $product->itemName = $item->getName();
        $product->itemPrice = $item->getPrice();
        $product->itemQuantity = $item->getQtyOrdered();
        $product->itemUrl = $item->getProduct()->getProductUrl();
        $product->itemWeight = $item->getProduct()->getWeight();
        return $product;
    }

    /**
     * @param $order Order
     * @return Purchase
     */
    protected function makePurchase(Order $order)
    {
        // Get all of the purchased products
        $items = $order->getAllItems();
        $purchase = SignifydModel::Make("\\Signifyd\\Models\\Purchase");
        $purchase->orderChannel = "WEB";
        $purchase->products = array();
        foreach ($items as $item) {
            $purchase->products[] = $this->makeProduct($item);
        }

        $purchase->totalPrice = $order->getGrandTotal();
        $purchase->currency = $order->getOrderCurrencyCode();
        $purchase->orderId = $order->getIncrementId();
        $purchase->paymentGateway = $order->getPayment()->getMethod();
        $purchase->shippingPrice = floatval($order->getShippingAmount());
        $purchase->avsResponseCode = $order->getPayment()->getCcAvsStatus();
        $purchase->cvvResponseCode = $order->getPayment()->getCcSecureVerify();
        $purchase->createdAt = date('c', strtotime($order->getCreatedAt()));;

        $purchase->browserIpAddress = $this->getIPAddress($order);

        return $purchase;
    }

    /**
     * @param $mageAddress Address
     * @return SignifydAddress
     */
    protected function formatSignifydAddress($mageAddress)
    {
        $address = SignifydModel::Make("\\Signifyd\\Models\\Address");

        $address->streetAddress = $mageAddress->getStreetLine(1);
        $address->unit = $mageAddress->getStreetLine(2);

        $address->city = $mageAddress->getCity();

        $address->provinceCode = $mageAddress->getRegionCode();
        $address->postalCode = $mageAddress->getPostcode();
        $address->countryCode = $mageAddress->getCountryId();

        $address->latitude = null;
        $address->longitude = null;

        return $address;
    }

    /**
     * @param $order Order
     * @return Recipient|null
     */
    protected function makeRecipient(Order $order)
    {
        $address = $order->getShippingAddress();

        if ($address == null) {
            return null;
        }

        $recipient = SignifydModel::Make("\\Signifyd\\Models\\Recipient");
        $recipient->deliveryAddress = $this->formatSignifydAddress($address);
        $recipient->fullName = $address->getName();
        $recipient->confirmationPhone = $address->getTelephone();
        $recipient->confirmationEmail = $address->getEmail();
        return $recipient;
    }

    /**
     * @param $order Order
     * @return Card|null
     */
    protected function makeCardInfo(Order $order)
    {
        $payment = $order->getPayment();
        $this->_logger->debug($payment->convertToJson());
        if (!(is_subclass_of($payment->getMethodInstance(), '\Magento\Payment\Model\Method\Cc'))) {
            return null;
        }

        $card = SignifydModel::Make("\\Signifyd\\Models\\Card");
        $card->cardholderName = $payment->getCcOwner();
        $card->last4 = $payment->getCcLast4();
        $card->expiryMonth = $payment->getCcExpMonth();
        $card->expiryYear = $payment->getCcExpYear();
        $card->hash = $payment->getCcNumberEnc();

        $ccNum = $payment->getData('cc_number');
        if ($ccNum && is_numeric($ccNum) && strlen((string)$ccNum) > 6) {
            $card->bin = substr((string)$ccNum, 0, 6);
        }

        $card->billingAddress = $this->formatSignifydAddress($order->getBillingAddress());
        return $card;
    }

    /** Construct a user account blob
     * @param $order Order
     * @return UserAccount
     */
    protected function makeUserAccount(Order $order)
    {
        /* @var $user \Signifyd\Models\UserAccount */
        $user = SignifydModel::Make("\\Signifyd\\Models\\UserAccount");
        $user->emailAddress = $order->getCustomerEmail();
        $user->username = $order->getCustomerEmail();
        $user->accountNumber = $order->getCustomerId();
        $user->phone = $order->getBillingAddress()->getTelephone();

        /* @var $customer \Magento\Customer\Model\Customer */
        $customer = $this->_objectManager->get('Magento\Customer\Model\Customer')->load($order->getCustomerId());
        $this->_logger->debug("Customer data: " . json_encode($customer));
        if(!is_null($customer) && !$customer->isEmpty()) {
            $user->createdDate = date('c', strtotime($customer->getCreatedAt()));
        }
        /** @var $orderFactory \Magento\Sales\Model\ResourceModel\Order\Collection */
        $orders = $this->_objectManager->get('\Magento\Sales\Model\ResourceModel\Order\Collection');
        $orders->addFieldToFilter('customer_id', $order->getCustomerId());
        $orders->load();

        $orderCount = 0;
        $orderTotal = 0.0;
        /** @var $o \Magento\Sales\Model\Order*/
        foreach($orders as $o) {
            $orderCount++;
            $orderTotal += floatval($o->getGrandTotal());
        }

        $user->aggregateOrderCount = $orderCount;
        $user->aggregateOrderDollars = $orderTotal;

        return $user;
    }

    /**
     * Loading the case
     * @param Order $order
     * @return \Signifyd\Connect\Model\Casedata
     */
    public function getCase(Order $order)
    {
        /** @var $case \Signifyd\Connect\Model\Casedata */
        $case = $this->_objectManager->get('Signifyd\Connect\Model\Casedata');
        $case->load($order->getIncrementId());
        return $case;
    }

    /**
     * Check if the related case exists
     * @param Order $order
     * @return bool
     */
    public function doesCaseExist(Order $order)
    {
        /** @var $case \Signifyd\Connect\Model\Casedata */
        $case = $this->getCase($order);
        return !($case->isEmpty() || $case->isObjectNew());
    }

    /**
     * Construct a new case object
     * @param $order Order
     * @return CaseModel
     */
    public function processOrderData($order)
    {
        $case = SignifydModel::Make("\\Signifyd\\Models\\CaseModel");
        $case->card = $this->makeCardInfo($order);
        $case->purchase = $this->makePurchase($order);
        $case->recipient = $this->makeRecipient($order);
        $case->userAccount = $this->makeUserAccount($order);
        $case->clientVersion = $this->getVersions();
        return $case;
    }

    /**
     * Saving the case to the database
     * @param $order
     * @return \Signifyd\Connect\Model\Casedata
     */
    public function createNewCase($order)
    {
        /** @var $case \Signifyd\Connect\Model\Casedata */
        $case = $this->_objectManager->create('Signifyd\Connect\Model\Casedata');
        $case->setId($order->getIncrementId())
            ->setSignifydStatus("PENDING")
            ->setCreated(strftime('%Y-%m-%d %H:%M:%S', time()))
            ->setUpdated(strftime('%Y-%m-%d %H:%M:%S', time()))
            ->setEntriesText("");
        $case->save();
        return $case;
    }

    /**
     * @param $caseData
     * @return bool
     */
    public function postCaseToSignifyd($caseData, $order)
    {
        $this->_logger->request("Sending: " . json_encode($caseData));
        $id = $this->_api->createCase($caseData);

        if ($id) {
            $this->_logger->debug("Case sent. Id is $id");
        } else {
            $this->_logger->error("Case failed to send.");
            return false;
        }

        return $id;
    }
}
