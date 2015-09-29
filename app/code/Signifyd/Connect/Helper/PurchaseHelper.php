<?php

namespace Signifyd\Connect\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address;
use \Magento\Framework\ObjectManagerInterface;
use Magento\Sales\Model\Order\Item;
use Psr\Log\LoggerInterface;
use Signifyd\Connect\Lib\SDK\Core\SignifydAPI;
use Signifyd\Connect\Lib\SDK\Core\SignifydSettings;
use Signifyd\Connect\Lib\SDK\Models\Address as SignifydAddress;
use Signifyd\Connect\Lib\SDK\Models\Card;
use Signifyd\Connect\Lib\SDK\Models\CaseModel;
use Signifyd\Connect\Lib\SDK\Models\Product;
use Signifyd\Connect\Lib\SDK\Models\Purchase;
use Signifyd\Connect\Lib\SDK\Models\Recipient;
use Signifyd\Connect\Lib\SDK\Models\UserAccount;

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
     * @var \Signifyd\Connect\Lib\SDK\core\SignifydAPI
     */
    protected $_api;

    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $_objectManager;

    public function __construct(
        ObjectManagerInterface $objectManager,
        LoggerInterface $logger,
        ScopeConfigInterface $scopeConfig
    )
    {
        $this->_logger = new LogHelper($logger, $scopeConfig);
        $this->_objectManager = $objectManager;
        try {
            $settings = new SignifydSettings();
            $settings->apiKey = $scopeConfig->getValue('signifyd/general/key');

            $settings->logInfo = true;
            $settings->loggerInfo = function($message) { $this->_logger->debug($message); };
            $settings->loggerError = function($message) { $this->_logger->error($message); };
            $settings->apiAddress = $scopeConfig->getValue('signifyd/general/url');
            $this->_api = new SignifydAPI($settings);

        } catch (\Exception $e) {
            $this->_logger->error($e);
        }

    }

    private function getIPAddress(Order $order)
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

    private function filterIp($ip)
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
    private function makeProduct(Item $item)
    {
        // TODO need to investigate this further
        $product = new Product();
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
    private function makePurchase(Order $order)
    {
        $this->_logger->debug("makePurchase");

        // Get all of the purchased products
        $items = $order->getAllItems();
        $purchase = new Purchase();
        $purchase->products = array();
        foreach($items as $item) {
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

        $purchase->orderChannel;
        $purchase->shipments;
        $purchase->receivedBy;

        return $purchase;
    }

    /**
     * @param $mageAddress Address
     * @return SignifydAddress
     */
    private function formatSignifydAddress($mageAddress)
    {
        $this->_logger->debug("formatSignifydAddress");
        $address = new SignifydAddress();

        $address->streetAddress = $mageAddress->getStreetLine(1);
        $address->unit = $mageAddress->getStreetLine(2);

        $address->city = $mageAddress->getCity();

        $address->provinceCode = $mageAddress->getRegionCode();
        $address->postalCode = $mageAddress->getPostcode();
        $address->countryCode = $mageAddress->getCountryId();

        $address->latitude = null;
        $address->longitude = null;

        $this->_logger->debug("/formatSignifydAddress ".json_encode($address));
        return $address;
    }

    /**
     * @param $order Order
     * @return Recipient|null
     */
    private function makeRecipient(Order $order)
    {
        $this->_logger->debug("makeRecipient");
        $address = $order->getShippingAddress();

        if($address == null) return null;

        $recipient = new Recipient();
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
    private function makeCardInfo(Order $order)
    {
        $this->_logger->debug("makeCardInfo");
        $payment = $order->getPayment();
        $this->_logger->debug($payment->convertToJson());
        if(!(is_subclass_of($payment->getMethodInstance(), '\Magento\Payment\Model\Method\Cc')))
        {
            return null;
        }

        $card = new Card();
        $card->cardholderName = $payment->getCcOwner();
        $card->last4 = $payment->getCcLast4();
        $card->expiryMonth = $payment->getCcExpMonth();
        $card->expiryYear = $payment->getCcExpYear();
        $card->hash = $payment->getCcNumberEnc();

        $ccNum = $payment->getData('cc_number');
        if($ccNum && is_numeric($ccNum) && strlen((string)$ccNum) > 6) {
            $card->bin = substr((string)$ccNum, 0, 6);
        }

        $card->billingAddress = $this->formatSignifydAddress($order->getBillingAddress());
        return $card;
    }

    /** Construct a user account blob
     * @param $order Order
     * @return UserAccount
     */
    private function makeUserAccount(Order $order)
    {
        $this->_logger->debug("makeUserAccount");
        $user = new UserAccount();
        $user->emailAddress = $order->getCustomerEmail();
        $user->accountNumber = $order->getCustomerId();
        $user->phone = $order->getBillingAddress()->getTelephone();
        return $user;
    }

    /** Construct a new case object
     * @param $order Order
     * @return CaseModel
     */
    public function processOrderData($order)
    {
        $this->_logger->debug("processOrderData");
        $case = new CaseModel();
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
             ->setCode("NA")
             ->setCreated(strftime('%Y-%m-%d %H:%M:%S', time()))
             ->setUpdated(strftime('%Y-%m-%d %H:%M:%S', time()))
             ->setEntriesText("");
        $this->_logger->debug($case->convertToJson());
        $case->save();
    }

    /**
     * @param $caseData
     */
    public function postCaseToSignifyd($caseData)
    {
        $this->_logger->request("Sending: ".json_encode($caseData));
        $id = $this->_api->createCase($caseData);
        if($id) {
            $this->_logger->debug("Case sent. Id is $id");
        } else {
            $this->_logger->error("Case failed to send.");
        }
    }
}
