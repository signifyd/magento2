<?php
/**
 * Copyright 2015 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Signifyd\Connect\Helper;

use Braintree\Exception;
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
use \Signifyd\Connect\Helper\DeviceHelper;
use Signifyd\Connect\Model\PaymentVerificationFactory;
//use Magento\Signifyd\Model\PaymentVerificationFactory;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Framework\Registry;

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
     * @var \Signifyd\Connect\Helper\DeviceHelper
     */
    protected $_deviceHelper;

    /**
     * @var PaymentVerificationFactory
     */
    protected $paymentVerificationFactory;

    protected $registry;

    /**
     * @param ObjectManagerInterface $objectManager
     * @param LogHelper $logger
     * @param DateTime $dateTime
     * @param ScopeConfigInterface $scopeConfig
     * @param SignifydAPIMagento $api
     * @param ModuleListInterface $moduleList
     * @param DeviceHelper $deviceHelper
     * @param PaymentVerificationFactory $paymentVerificationFactory
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        LogHelper $logger,
        DateTime $dateTime,
        ScopeConfigInterface $scopeConfig,
        SignifydAPIMagento $api,
        ModuleListInterface $moduleList,
        DeviceHelper $deviceHelper,
        PaymentVerificationFactory $paymentVerificationFactory,
        Registry $registry
    ) {
        $this->_logger = $logger;
        $this->_objectManager = $objectManager;
        $this->_moduleList = $moduleList;
        $this->_deviceHelper = $deviceHelper;
        $this->paymentVerificationFactory = $paymentVerificationFactory;
        $this->registry = $registry;

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
    protected function filterIp($ipString)
    {
        $matches = array();

        $pattern = '(([0-9]{1,3}(?:\.[0-9]{1,3}){3})|([0-9a-fA-F]{1,4}:){7,7}[0-9a-fA-F]{1,4}|([0-9a-fA-F]{1,4}:){1,7}:|([0-9a-fA-F]{1,4}:){1,6}:[0-9a-fA-F]{1,4}|([0-9a-fA-F]{1,4}:){1,5}(:[0-9a-fA-F]{1,4}){1,2}|([0-9a-fA-F]{1,4}:){1,4}(:[0-9a-fA-F]{1,4}){1,3}|([0-9a-fA-F]{1,4}:){1,3}(:[0-9a-fA-F]{1,4}){1,4}|([0-9a-fA-F]{1,4}:){1,2}(:[0-9a-fA-F]{1,4}){1,5}|[0-9a-fA-F]{1,4}:((:[0-9a-fA-F]{1,4}){1,6})|:((:[0-9a-fA-F]{1,4}){1,7}|:)|fe80:(:[0-9a-fA-F]{0,4}){0,4}%[0-9a-zA-Z]{1,}|::(ffff(:0{1,4}){0,1}:){0,1}((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])|([0-9a-fA-F]{1,4}:){1,4}:((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9]))';

        preg_match_all($pattern, $ipString, $matches);

        if (is_array($matches)) return $matches[0][0];

        return null;
    }

    /**
     * Getting the version of Magento and the version of the extension
     * @return array
     */
    protected function getVersions()
    {
        $version = array();
        $productMetadata = $this->_objectManager->get('\Magento\Framework\App\ProductMetadata');
        $version['storePlatformVersion'] = $productMetadata->getVersion();
        $version['signifydClientApp'] = 'Magento 2';
        $version['storePlatform'] = 'Magento 2';
        $version['signifydClientAppVersion'] = (string)($this->_moduleList->getOne('Signifyd_Connect')['setup_version']);
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
        $product->itemQuantity = (int)$item->getQtyOrdered();
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
        $purchase->avsResponseCode = $this->getAvsCode($order->getPayment());
        $purchase->cvvResponseCode = $this->getCvvCode($order->getPayment());

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
        $purchase->createdAt = date('c', strtotime($order->getCreatedAt()));
        $purchase->browserIpAddress = $this->getIPAddress($order);

        if (!$this->isAdmin() && $this->_deviceHelper->isDeviceFingerprintEnabled()) {
            $purchase->orderSessionId = $this->_deviceHelper->generateFingerprint($order->getQuoteId());
        }

        return $purchase;
    }

    public function isAdmin()
    {
        /** @var \Magento\Framework\ObjectManagerInterface $om */
        $om = \Magento\Framework\App\ObjectManager::getInstance();
        /** @var \Magento\Framework\App\State $state */
        $state =  $om->get('Magento\Framework\App\State');
        return 'adminhtml' === $state->getAreaCode();
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

        $billingAddress = $order->getBillingAddress();
        $card = SignifydModel::Make("\\Signifyd\\Models\\Card");
        $card->cardHolderName = $this->getCardholder($order);
        $card->bin = $this->getBin($order->getPayment());
        $card->last4 = $this->getLast4($order->getPayment());
        $card->expiryMonth = $this->getExpMonth($order->getPayment());
        $card->expiryYear = $this->getExpYear($order->getPayment());

        $card->billingAddress = $this->formatSignifydAddress($billingAddress);
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
        /** @var $orders \Magento\Sales\Model\ResourceModel\Order\Collection */
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

        /**
         * This registry entry it's used to collect data from some payment methods like Payflow Link
         * It must be unregistered after use
         * @see \Signifyd\Connect\Plugin\Magento\Paypal\Model\Payflowlink
         */
        $this->registry->unregister('signifyd_payment_data');

        return $case;
    }

    /**
     * Saving the case to the database
     * @param \Magento\Sales\Model\Order $order
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

    /**
     * @param Order $order
     * @return bool
     */
    public function cancelCaseOnSignifyd(Order $order)
    {
        $case = $this->getCase($order);
        if ($case->isEmpty()) {
            $this->_logger->debug("Guarantee cancel skipped: case not found for order " . $order->getIncrementId());
            return false;
        }

        $this->_logger->debug('Cancelling case ' . $case->getId());

        $guarantee = $case->getData('guarantee');
        if (empty($guarantee) || in_array($guarantee, array('DECLINED', 'N/A'))) {
            $this->_logger->debug("Guarantee cancel skipped: current guarantee is {$guarantee}");
            return false;
        }

        $disposition = $this->_api->cancelGuarantee($case->getCode());
        $this->_logger->debug("Cancel disposition result {$disposition}");

        if ($disposition == 'CANCELED') {
            $case->setData('guarantee', $disposition);
            $case->save();

            $order->setSignifydGuarantee($disposition);
            $order->save();
            return true;
        }

        return false;
    }

    /**
     * Check if case has guaranty
     * @param $order
     * @return bool
     */
    public function hasGuaranty($order)
    {
        $case = $this->getCase($order);
        return ($case->getGuarantee() == 'N/A')? false : true;
    }

    /**
     * Gets AVS code for order payment method.
     *
     * @param OrderPaymentInterface $orderPayment
     * @return string
     */
    protected function getAvsCode(OrderPaymentInterface $orderPayment)
    {
        try {
            $avsAdapter = $this->paymentVerificationFactory->createPaymentAvs($orderPayment->getMethod());
            $avsCode = $avsAdapter->getData($orderPayment);
            $avsCode = trim(strtoupper($avsCode));
            
            if (empty($avsCode) || strlen($avsCode) > 1) {
                return null;
            }

            // http://www.emsecommerce.net/avs_cvv2_response_codes.htm from Signifyd Api Documentation
            $validAvsResponseCodes = array('X', 'Y', 'A', 'W', 'Z', 'N', 'U', 'R', 'E', 'S', 'D', 'M', 'B', 'P', 'C', 'I', 'G');

            if (in_array($avsCode, $validAvsResponseCodes)) {
                return $avsCode;
            } else {
                return null;
            }
        } catch (Exception $e) {
            $this->_logger->error('Error fetching AVS code: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Gets CVV code for order payment method.
     *
     * @param OrderPaymentInterface $orderPayment
     * @return string
     */
    protected function getCvvCode(OrderPaymentInterface $orderPayment)
    {
        try {
            $cvvAdapter = $this->paymentVerificationFactory->createPaymentCvv($orderPayment->getMethod());
            $cvvCode = $cvvAdapter->getData($orderPayment);
            $cvvCode = trim(strtoupper($cvvCode));

            if (empty($cvvCode) || strlen($cvvCode) > 1) {
                return null;
            }

            // http://www.emsecommerce.net/cvv_cvv2_response_codes.htm from Signifyd Api Documentation
            $validCvvResponseCodes = array('M', 'N', 'P', 'S', 'U');

            if (in_array($cvvCode, $validCvvResponseCodes)) {
                return $cvvCode;
            } else {
                return null;
            }
        } catch (Exception $e) {
            $this->_logger->error('Error fetching CVV code: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Gets cardholder for order
     *
     * @param Order $order
     * @return string
     */
    protected function getCardholder(Order $order)
    {
        try {
            $cardholderAdapter = $this->paymentVerificationFactory->createPaymentCardholder($order->getPayment()->getMethod());
            $cardholder = $cardholderAdapter->getData($order->getPayment());

            if (empty($cardholder)) {
                $firstname = $order->getBillingAddress()->getFirstname();
                $lastname = $order->getBillingAddress()->getLastname();
                $cardholder = trim($firstname) . ' ' . trim($lastname);
            }

            $cardholder = strtoupper($cardholder);
            $cardholder = preg_replace('/[^A-Z ]/', '', $cardholder);
            $cardholder = preg_replace( '/  +/', ' ', $cardholder);

            return $cardholder;
        } catch (Exception $e) {
            $this->_logger->error('Error fetching cardholder: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Gets last4 for order payment method.
     *
     * @param OrderPaymentInterface $orderPayment
     * @return string|null
     */
    protected function getLast4(OrderPaymentInterface $orderPayment)
    {
        try {
            $last4Adapter = $this->paymentVerificationFactory->createPaymentLast4($orderPayment->getMethod());
            $last4 = $last4Adapter->getData($orderPayment);
            $last4 = preg_replace('/\D/', '', $last4);

            if (!empty($last4) && strlen($last4) == 4 && is_numeric($last4)) {
                return strval($last4);
            }

            return null;
        } catch (Exception $e) {
            $this->_logger->error('Error fetching last4: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Gets expiration month for order payment method.
     *
     * @param OrderPaymentInterface $orderPayment
     * @return int|null
     */
    protected function getExpMonth(OrderPaymentInterface $orderPayment)
    {
        try {
            $monthAdapter = $this->paymentVerificationFactory->createPaymentExpMonth($orderPayment->getMethod());
            $expMonth = $monthAdapter->getData($orderPayment);
            $expMonth = preg_replace('/\D/', '', $expMonth);

            $expMonth = intval($expMonth);
            if ($expMonth < 1 || $expMonth > 12) {
                return null;
            }

            return $expMonth;
        } catch (Exception $e) {
            $this->_logger->error('Error fetching expiration month: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Gets expiration year for order payment method.
     *
     * @param OrderPaymentInterface $orderPayment
     * @return int|null
     */
    protected function getExpYear(OrderPaymentInterface $orderPayment)
    {
        try {
            $yearAdapter = $this->paymentVerificationFactory->createPaymentExpYear($orderPayment->getMethod());
            $expYear = $yearAdapter->getData($orderPayment);
            $expYear = preg_replace('/\D/', '', $expYear);

            $expYear = intval($expYear);
            if ($expYear <= 0) {
                return null;
            }

            //If returned expiry year has less then 4 digits
            if ($expYear < 1000) {
                $expYear += 2000;
            }

            return $expYear;
        } catch (Exception $e) {
            $this->_logger->error('Error fetching expiration year: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Gets credit card bin for order payment method.
     *
     * @param OrderPaymentInterface $orderPayment
     * @return int|null
     */
    protected function getBin(OrderPaymentInterface $orderPayment)
    {
        try {
            $binAdapter = $this->paymentVerificationFactory->createPaymentBin($orderPayment->getMethod());
            $bin = $binAdapter->getData($orderPayment);
            $bin = preg_replace('/\D/', '', $bin);

            if (empty($bin)) {
                return null;
            }

            $bin = intval($bin);
            // A credit card does not starts with zero, so the bin intaval has to be at least 100.000
            if ($bin < 100000) {
                return null;
            }

            return $bin;
        } catch (Exception $e) {
            $this->_logger->error('Error fetching expiration bin: ' . $e->getMessage());
            return null;
        }
    }
}
