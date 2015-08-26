<?php

namespace Signifyd\Connect\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address;
use \Magento\Framework\ObjectManagerInterface;
use Psr\Log\LoggerInterface;
use Signifyd\Connect\Lib\SDK\core\SignifydAPI;
use Signifyd\Connect\Lib\SDK\core\SignifydSettings;

/**
 * Class PurchaseHelper
 * Handles the conversion from Magento Order to Signifyd Case and sends to Signifyd service.
 * @package Signifyd\Connect\Helper
 */
class PurchaseHelper
{
    /**
     * @var \Psr\Log\LoggerInterface
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
        $this->_logger = $logger;
        try {
            $settings = new SignifydSettings();
            $settings->apiKey = $scopeConfig->getValue('signifyd/general/key');
            if(!$settings->apiKey)
            {
                $settings->apiKey = "ABCDE";
            }
            $settings->logInfo = true;
            $this->_api = new SignifydAPI($settings);
            $this->_logger->info(json_encode($settings));
        } catch (\Exception $e) {
            $this->_logger->error($e);
        }

    }

    // TODO: right now these are being built adhoc. Need to switch to SDK models
    // Low relevance, however, until validation is setup

    /**
     * @param $order Order
     * @return array
     */
    private function makePurchase(Order $order)
    {
        return array();
    }

    /**
     * @param $mageAddress Address
     * @return array
     */
    private function formatSignifydAddress($mageAddress)
    {
        $this->_logger->info($mageAddress->convertToJson());
        $address = array();

        $address['streetAddress'] = $mageAddress->getStreet();
        $address['unit'] = null;

        $address['city'] = $mageAddress->getCity();

        $address['provinceCode'] = $mageAddress->getRegionCode();
        $address['postalCode'] = $mageAddress->getPostcode();
        $address['countryCode'] = $mageAddress->getCountryId();

        $address['latitude'] = null;
        $address['longitude'] = null;

        return $address;
    }

    /**
     * @param $order Order
     * @return array
     */
    private function makeRecipient(Order $order)
    {
        $address = $order->getShippingAddress();
        return array(
            "deliveryAddress" => $this->formatSignifydAddress($address),
            "fullName" => $address->getFirstname() . " " . $address->getLastname(),
            "confirmationPhone" => $address->getTelephone(),
            "confirmationEmail" => $address->getEmail()
        );
    }

    /**
     * @param $order Order
     * @return array
     */
    private function makeCardInfo(Order $order)
    {
        $payment = $order->getPayment();
        $this->_logger->info($payment->convertToJson());
        if(!(is_subclass_of($payment->getMethodInstance(), '\Magento\Payment\Model\Method\Cc')))
        {
            return array();
        }
        return array(
            "cardHolderName" => $payment->getCcOwner(),
            "last4" => $payment->getCcLast4(),
            "expiryMonth" => $payment->getCcExpMonth(),
            "expiryYear" => $payment->getCcExpYear(),
            "hash" => $payment->getCcNumberEnc(),
            "bin" => substr((string)$payment->getData('cc_number'), 0, 6),
            "billingAddress" => $this->formatSignifydAddress($order->getBillingAddress())
        );
    }

    /** Construct a user account blob
     * @param $order Order
     * @return array An array formatted for Signifyd
     */
    private function makeUserAccount(Order $order)
    {
        return array(
            "emailAddress" => $order->getCustomerEmail(),
            "accountNumber" => $order->getCustomerId(),
            "phone" => $order->getBillingAddress()->getTelephone()
        );
    }

    public function processOrderData($order)
    {
        return array(
            "card" => $this->makeCardInfo($order),
            "purchase" => $this->makePurchase($order),
            "recipient" => $this->makeRecipient($order),
            "userAccount" => $this->makeUserAccount($order)
        );
    }

    public function createNewCase($order)
    {
        /** @var $case \Signifyd\Connect\Model\Casedata */
        $case = $this->_objectManager->create('Signifyd\Connect\Model\Casedata');
        $case->setId(6 /*$order->getOrderIncrement()*/) // FILLER DATA. Webhooks not hooked in, so mostly irrelevant
             ->setSignifydStatus("PENDING")
             ->setCode("NA")
             ->setScore(500.0)
             ->setEntriesText("");
        $this->_logger->info($case->convertToJson());
        $case->save();
    }

    public function postCaseToSignifyd($caseData)
    {
        $id = $this->_api->createCase($caseData);
        $this->_logger->info("Case sent. Id is $id");
    }
}
