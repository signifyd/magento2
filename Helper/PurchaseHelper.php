<?php

namespace Signifyd\Connect\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address;
use \Magento\Framework\ObjectManagerInterface;
use Magento\Sales\Model\Order\Item;
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
     * @param ObjectManagerInterface $objectManager
     * @param LogHelper $logger
     * @param DateTime $dateTime
     * @param ScopeConfigInterface $scopeConfig
     * @param SignifydAPIMagento $api
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        LogHelper $logger,
        DateTime $dateTime,
        ScopeConfigInterface $scopeConfig,
        SignifydAPIMagento $api
    ) {
        $this->_logger = $logger;
        $this->_objectManager = $objectManager;
        try {
            $this->_api = $api;

        } catch (\Exception $e) {
            $this->_logger->error($e);
        }

    }

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
     * @param Item $item
     * @return Product
     */
    protected function makeProduct(Item $item)
    {
        $product = SignifydModel::Make("\\Signifyd\\Models\\Product");
        $product->itemId = $item->getSku();
        $product->itemName = $item->getName();
        $product->itemPrice = $item->getPrice();
        $product->itemQuality = $item->getQtyOrdered();
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
        $purchase->products = array();
        foreach ($items as $item) {
            $purchase->products[] = $this->makeProduct($item);
        }

        $purchase->totalPrice = $order->getGrandTotal();
        $purchase->currency = $order->getOrderCurrencyCode();
        $purchase->orderId = $order->getIncrementId();
        $purchase->paymentGateway = $order->getPayment()->getMethod();
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
        $user = SignifydModel::Make("\\Signifyd\\Models\\UserAccount");
        $user->emailAddress = $order->getCustomerEmail();
        $user->accountNumber = $order->getCustomerId();
        $user->phone = $order->getBillingAddress()->getTelephone();
        return $user;
    }

    public function doesCaseExist(Order $order)
    {
        /** @var $case \Signifyd\Connect\Model\Casedata */
        $case = $this->_objectManager->get('Signifyd\Connect\Model\Casedata');
        $case->load($order->getIncrementId());
        return !($case->isEmpty() || $case->isObjectNew());
    }

    /** Construct a new case object
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
        return $case;
    }

    /**
     * @param $order
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
    }

    /**
     * @param $caseData
     */
    public function postCaseToSignifyd($caseData)
    {
        $this->_logger->request("Sending: " . json_encode($caseData));
        $id = $this->_api->createCase($caseData);
        if ($id) {
            $this->_logger->debug("Case sent. Id is $id");
        } else {
            $this->_logger->error("Case failed to send.");
        }
    }
}
