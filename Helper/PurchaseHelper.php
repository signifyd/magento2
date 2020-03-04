<?php
/**
 * Copyright 2015 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Signifyd\Connect\Helper;

use Braintree\Exception;
use Magento\Framework\Module\ModuleListInterface;
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
use Signifyd\Connect\Model\PaymentVerificationFactory;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Framework\Registry;
use Signifyd\Connect\Logger\Logger;

/**
 * Class PurchaseHelper
 * Handles the conversion from Magento Order to Signifyd Case and sends to Signifyd service.
 */
class PurchaseHelper
{
    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var \Signifyd\Connect\Helper\ConfigHelper
     */
    protected $configHelper;

    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $objectManager;

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
     * @param ObjectManagerInterface $objectManager
     * @param Logger $logger
     * @param ConfigHelper $configHelper
     * @param ModuleListInterface $moduleList
     * @param DeviceHelper $deviceHelper
     * @param PaymentVerificationFactory $paymentVerificationFactory
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        Logger $logger,
        ConfigHelper $configHelper,
        ModuleListInterface $moduleList,
        DeviceHelper $deviceHelper,
        PaymentVerificationFactory $paymentVerificationFactory,
        Registry $registry
    ) {
        $this->logger = $logger;
        $this->objectManager = $objectManager;
        $this->moduleList = $moduleList;
        $this->deviceHelper = $deviceHelper;
        $this->paymentVerificationFactory = $paymentVerificationFactory;
        $this->registry = $registry;
        $this->configHelper = $configHelper;
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
        $remoteAddressHelper = $this->objectManager->get(\Magento\Framework\HTTP\PhpEnvironment\RemoteAddress::class);
        return $this->filterIp($remoteAddressHelper->getRemoteAddress());
    }

    /**
     * Filter the ip address
     * @param $ip
     * @return mixed
     */
    protected function filterIp($ipString)
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
     * Getting the version of Magento and the version of the extension
     * @return array
     */
    protected function getVersions()
    {
        $version = [];
        $productMetadata = $this->objectManager->get(\Magento\Framework\App\ProductMetadata::class);
        $version['storePlatformVersion'] = $productMetadata->getVersion();
        $version['signifydClientApp'] = 'Magento 2';
        $version['storePlatform'] = 'Magento 2';
        $version['signifydClientAppVersion'] = (string)($this->moduleList->getOne('Signifyd_Connect')['setup_version']);
        return $version;
    }

    /**
     * @param Item $item
     * @return Product
     */
    protected function makeProduct(Item $item)
    {
        $product = SignifydModel::Make(\Signifyd\Models\Product::class);
        $product->itemId = $item->getSku();
        $product->itemName = $item->getName();
        $product->itemIsDigital = (bool) $item->getIsVirtual();
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
        $originStoreCode = $order->getData('origin_store_code');

        // Get all of the purchased products
        $items = $order->getAllItems();
        $purchase = SignifydModel::Make(\Signifyd\Models\Purchase::class);
        $purchase->avsResponseCode = $this->getAvsCode($order);
        $purchase->cvvResponseCode = $this->getCvvCode($order);

        if ($originStoreCode == 'admin') {
            $purchase->orderChannel = "PHONE";
        } elseif (!empty($originStoreCode)) {
            $purchase->orderChannel = "WEB";
        }

        $purchase->products = [];

        /** @var \Magento\Sales\Model\Order\Item $item */
        foreach ($items as $item) {
            $children = $item->getChildrenItems();

            if (is_array($children) == false || empty($children)) {
                $purchase->products[] = $this->makeProduct($item);
            }
        }

        $purchase->totalPrice = $order->getGrandTotal();
        $purchase->currency = $order->getOrderCurrencyCode();
        $purchase->orderId = $order->getIncrementId();
        $purchase->paymentGateway = $order->getPayment()->getMethod();
        $purchase->transactionId = $this->getTransactionId($order);
        $purchase->createdAt = date('c', strtotime($order->getCreatedAt()));
        $purchase->browserIpAddress = $this->getIPAddress($order);

        $couponCode = $order->getCouponCode();
        if (!empty($couponCode)) {
            $purchase->discountCodes = [
                'amount' => abs($order->getDiscountAmount()),
                'code' => $couponCode
            ];
        }

        $purchase->shipments = $this->makeShipments($order);

        if (!empty($originStoreCode) &&
            $originStoreCode != 'admin' &&
            $this->deviceHelper->isDeviceFingerprintEnabled()
        ) {
            $purchase->orderSessionId = $this->deviceHelper->generateFingerprint($order->getQuoteId());
        }

        return $purchase;
    }

    protected function makeShipments(Order $order)
    {
        $shipments = [];
        $shippingMethod = $order->getShippingMethod();

        if (!empty($shippingMethod)) {
            $shippingMethod = $order->getShippingMethod(true);
            $shipment = SignifydModel::Make(\Signifyd\Models\Shipment::class);
            $shipment->shipper = $shippingMethod->getCarrierCode();
            $shipment->shippingPrice = floatval($order->getShippingAmount());
            $shipment->shippingMethod = $shippingMethod->getMethod();

            $shipments[] = $shipment;
        }

        return $shipments;
    }

    public function isAdmin()
    {
        /** @var \Magento\Framework\ObjectManagerInterface $om */
        $om = \Magento\Framework\App\ObjectManager::getInstance();
        /** @var \Magento\Framework\App\State $state */
        $state =  $om->get(\Magento\Framework\App\State::class);
        return 'adminhtml' === $state->getAreaCode();
    }

    /**
     * @param $mageAddress Address
     * @return SignifydAddress
     */
    protected function formatSignifydAddress($mageAddress)
    {
        $address = SignifydModel::Make(\Signifyd\Models\Address::class);

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
        $recipient = SignifydModel::Make(\Signifyd\Models\Recipient::class);

        $address = $order->getShippingAddress();

        if ($address !== null) {
            $recipient->fullName = $address->getName();
            $recipient->confirmationEmail = $address->getEmail();
            $recipient->confirmationPhone = $address->getTelephone();
            $recipient->organization = $address->getCompany();
            $recipient->deliveryAddress = $this->formatSignifydAddress($address);
        }

        if (empty($recipient->fullName)) {
            $recipient->fullName = $order->getCustomerName();
        }

        if (empty($recipient->confirmationEmail)) {
            $recipient->confirmationEmail = $order->getCustomerEmail();
        }

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
        $card = SignifydModel::Make(\Signifyd\Models\Card::class);
        $card->cardHolderName = $this->getCardholder($order);
        $card->bin = $this->getBin($order);
        $card->last4 = $this->getLast4($order);
        $card->expiryMonth = $this->getExpMonth($order);
        $card->expiryYear = $this->getExpYear($order);

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
        $user = SignifydModel::Make(\Signifyd\Models\UserAccount::class);
        $user->emailAddress = $order->getCustomerEmail();
        $user->username = $order->getCustomerEmail();
        $user->accountNumber = $order->getCustomerId();
        $user->phone = $order->getBillingAddress()->getTelephone();

        /* @var $customer \Magento\Customer\Model\Customer */
        $customer = $this->objectManager->get(\Magento\Customer\Model\Customer::class)->load($order->getCustomerId());
        $this->logger->debug("Customer data: " . json_encode($customer), ['entity' => $order]);
        if ($customer !== null && !$customer->isEmpty()) {
            $user->createdDate = date('c', strtotime($customer->getCreatedAt()));
        }
        /** @var $orders \Magento\Sales\Model\ResourceModel\Order\Collection */
        $orders = $this->objectManager->get(\Magento\Sales\Model\ResourceModel\Order\Collection::class);
        $orders->addFieldToFilter('customer_id', $order->getCustomerId());
        $orders->load();

        $orderCount = 0;
        $orderTotal = 0.0;
        /** @var $o \Magento\Sales\Model\Order*/
        foreach ($orders as $o) {
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
        $case = $this->objectManager->create(\Signifyd\Connect\Model\Casedata::class);
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
        return $case->isEmpty() == false && $case->isObjectNew() == false;
    }

    /**
     * Construct a new case object
     * @param $order Order
     * @return CaseModel
     */
    public function processOrderData($order)
    {
        $case = SignifydModel::Make(\Signifyd\Models\CaseModel::class);
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
        $case = $this->objectManager->create(\Signifyd\Connect\Model\Casedata::class);
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
     * @param Order $order
     * @return bool
     */
    public function postCaseToSignifyd($caseData, $order)
    {
        $id = $this->configHelper->getSignifydApi($order)->createCase($caseData);

        if ($id) {
            $this->logger->debug("Case sent. Id is $id", ['entity' => $order]);
            $order->addStatusHistoryComment("Signifyd: case created {$id}");
            $order->save();
            return $id;
        } else {
            $this->logger->error("Case failed to send.", ['entity' => $order]);
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

        $case = $this->getCase($order);

        if ($case->isEmpty()) {
            $message = 'Guarantee cancel skipped: case not found for order ' . $order->getIncrementId();
            $this->logger->debug($message, ['entity' => $order]);
            return false;
        }

        $guarantee = $case->getData('guarantee');

        if (empty($guarantee) || in_array($guarantee, ['DECLINED', 'N/A'])) {
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

        $this->logger->debug('Cancelling case ' . $case->getId(), ['entity' => $order]);
        $disposition = $this->configHelper->getSignifydApi($order)->cancelGuarantee($case->getCode());

        $this->logger->debug("Cancel disposition result {$disposition}", ['entity' => $order]);

        if ($disposition == 'CANCELED') {
            $case->setData('guarantee', $disposition);
            $case->save();

            $order->setSignifydGuarantee($disposition);
            $order->addStatusHistoryComment("Signifyd: guarantee canceled");
            $order->save();
            return true;
        } else {
            $order->addStatusHistoryComment("Signifyd: failed to cancel guarantee");
            $order->save();
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
     * @param Order $order
     * @return string
     */
    protected function getAvsCode(Order $order)
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
    protected function getCvvCode(Order $order)
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
    protected function getCardholder(Order $order)
    {
        try {
            $paymentMethod = $order->getPayment()->getMethod();
            $cardholderAdapter = $this->paymentVerificationFactory->createPaymentCardholder($paymentMethod);
            $cardholder = $cardholderAdapter->getData($order);

            if (empty($cardholder)) {
                $firstname = $order->getBillingAddress()->getFirstname();
                $lastname = $order->getBillingAddress()->getLastname();
                $cardholder = trim($firstname) . ' ' . trim($lastname);
            }

            $cardholder = strtoupper($cardholder);
            $cardholder = preg_replace('/[^A-Z ]/', '', $cardholder);
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
    protected function getLast4(Order $order)
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
    protected function getExpMonth(Order $order)
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
    protected function getExpYear(Order $order)
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
    protected function getBin(Order $order)
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
    protected function getTransactionId(Order $order)
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
