<?php

namespace Signifyd\Connect\Model\Api;

use Braintree\Exception;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\PaymentVerificationFactory;

class CheckoutPaymentDetails
{
    /**
     * @var AddressFactory
     */
    protected $addressFactory;

    /**
     * @var AccountHolderNameFactory
     */
    protected $accountHolderNameFactory;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var PaymentVerificationFactory
     */
    protected $paymentVerificationFactory;

    /**
     * @var AccountHolderTaxIdFactory
     */
    protected $accountHolderTaxIdFactory;

    /**
     * @var AccountHolderTaxIdCountryFactory
     */
    protected $accountHolderTaxIdCountryFactory;

    /**
     * @var AbaRoutingNumberFactory
     */
    protected $abaRoutingNumberFactory;

    /**
     * @var CardTokenProviderFactory
     */
    protected $cardTokenProviderFactory;

    /**
     * @var CardTokenFactory
     */
    protected $cardTokenFactory;

    /**
     * @var CardInstallmentsFactory
     */
    protected $cardInstallmentsFactory;

    /**
     * @var AccountLast4Factory
     */
    protected $accountLast4Factory;

    /**
     * @var CardBrandFactory
     */
    protected $cardBrandFactory;

    /**
     * @param AddressFactory $addressFactory
     * @param AccountHolderNameFactory $accountHolderNameFactory
     * @param Logger $logger
     * @param PaymentVerificationFactory $paymentVerificationFactory
     * @param AccountHolderTaxIdFactory $accountHolderTaxIdFactory
     * @param AccountHolderTaxIdCountryFactory $accountHolderTaxIdCountryFactory
     * @param AbaRoutingNumberFactory $abaRoutingNumberFactory
     * @param CardTokenProviderFactory $cardTokenProviderFactory
     * @param CardTokenFactory $cardTokenFactory
     * @param CardInstallmentsFactory $cardInstallmentsFactory
     * @param AccountLast4Factory $accountLast4Factory
     * @param CardBrandFactory $cardBrandFactory
     */
    public function __construct(
        AddressFactory $addressFactory,
        AccountHolderNameFactory $accountHolderNameFactory,
        Logger $logger,
        PaymentVerificationFactory $paymentVerificationFactory,
        AccountHolderTaxIdFactory $accountHolderTaxIdFactory,
        AccountHolderTaxIdCountryFactory $accountHolderTaxIdCountryFactory,
        AbaRoutingNumberFactory $abaRoutingNumberFactory,
        CardTokenProviderFactory $cardTokenProviderFactory,
        CardTokenFactory $cardTokenFactory,
        CardInstallmentsFactory $cardInstallmentsFactory,
        AccountLast4Factory $accountLast4Factory,
        CardBrandFactory $cardBrandFactory
    ) {
        $this->addressFactory = $addressFactory;
        $this->accountHolderNameFactory = $accountHolderNameFactory;
        $this->logger = $logger;
        $this->paymentVerificationFactory = $paymentVerificationFactory;
        $this->accountHolderTaxIdFactory = $accountHolderTaxIdFactory;
        $this->accountHolderTaxIdCountryFactory = $accountHolderTaxIdCountryFactory;
        $this->abaRoutingNumberFactory = $abaRoutingNumberFactory;
        $this->cardTokenProviderFactory = $cardTokenProviderFactory;
        $this->cardTokenFactory = $cardTokenFactory;
        $this->cardInstallmentsFactory = $cardInstallmentsFactory;
        $this->accountLast4Factory = $accountLast4Factory;
        $this->cardBrandFactory = $cardBrandFactory;
    }

    /**
     * Construct a new CheckoutPaymentDetails object
     * @param $entity Order|Quote
     * @return array
     */
    public function __invoke($entity, $methodData = [])
    {
        if ($entity instanceof Order) {
            $checkoutPaymentDetails = $this->makeCheckoutPaymentDetails($entity);
        } elseif ($entity instanceof Quote) {
            $checkoutPaymentDetails = $this->makeCheckoutPaymentDetailsFromQuote($entity, $methodData);
        } else {
            $checkoutPaymentDetails = [];
        }

        return $checkoutPaymentDetails;
    }

    /**
     * @param $order Order
     * @return array
     */
    protected function makeCheckoutPaymentDetails(Order $order)
    {
        $cardInstallmentsvalue = $this->cardInstallmentsFactory->create();
        $cardInstallments = $cardInstallmentsvalue();
        $cardholder = $this->accountHolderNameFactory->create();

        if (isset($cardInstallments) === false &&
            $order->getPayment()->getMethod() == 'openpay_cards' &&
            is_array($order->getPayment()->getData('additional_information')) &&
            isset($order->getPayment()->getData('additional_information')['interest_free']) &&
            $order->getPayment()->getData('additional_information')['interest_free'] > 1
        ) {
            $cardInstallments = [
                'interval' => 'Month',
                'count' => $order->getPayment()->getData('additional_information')['interest_free'],
                'totalValue' => $order->getGrandTotal()
            ];
        }

        $signifydAddress = $this->addressFactory->create();
        $accountHolderTaxId = $this->accountHolderTaxIdFactory->create();
        $accountHolderTaxIdCountry = $this->accountHolderTaxIdCountryFactory->create();
        $abaRoutingNumber = $this->abaRoutingNumberFactory->create();
        $cardTokenProvider = $this->cardTokenProviderFactory->create();
        $cardToken = $this->cardTokenFactory->create();
        $accountLast4 = $this->accountLast4Factory->create();
        $cardBrand = $this->cardBrandFactory->create();
        $billingAddress = $order->getBillingAddress();

        $checkoutPaymentDetails = [];
        $checkoutPaymentDetails['billingAddress'] = $signifydAddress($billingAddress);
        $checkoutPaymentDetails['accountHolderName'] = $cardholder($order);
        $checkoutPaymentDetails['accountHolderTaxId'] = $accountHolderTaxId();
        $checkoutPaymentDetails['accountHolderTaxIdCountry'] = $accountHolderTaxIdCountry();
        $checkoutPaymentDetails['accountLast4'] = $accountLast4();
        $checkoutPaymentDetails['abaRoutingNumber'] = $abaRoutingNumber();
        $checkoutPaymentDetails['cardToken'] = $cardToken();
        $checkoutPaymentDetails['cardTokenProvider'] = $cardTokenProvider();
        $checkoutPaymentDetails['cardBin'] = $this->getBin($order);
        $checkoutPaymentDetails['cardExpiryMonth'] = $this->getExpMonth($order);
        $checkoutPaymentDetails['cardExpiryYear'] = $this->getExpYear($order);
        $checkoutPaymentDetails['cardLast4'] = $this->getLast4($order);
        $checkoutPaymentDetails['cardBrand'] = $cardBrand();
        $checkoutPaymentDetails['cardInstallments'] = $cardInstallments;

        return $checkoutPaymentDetails;
    }

    /**
     * @param Quote $quote
     * @param $methodData
     * @return array
     */
    protected function makeCheckoutPaymentDetailsFromQuote(Quote $quote, $methodData = [])
    {
        $checkoutPaymentDetails = [];
        $signifydAddress = $this->addressFactory->create();
        $cardholder = $this->accountHolderNameFactory->create();

        if (is_array($methodData)) {
            $checkoutPaymentDetails['cardLast4'] = $methodData['cardLast4'] ?? null;
            $checkoutPaymentDetails['cardExpiryMonth'] = $methodData['cardExpiryMonth'] ?? null;
            $checkoutPaymentDetails['cardExpiryYear'] = $methodData['cardExpiryYear'] ?? null;
        }

        $billingAddress = $quote->getBillingAddress();
        $accountHolderTaxIdCountry = $this->accountHolderTaxIdCountryFactory->create();
        $accountHolderTaxId = $this->accountHolderTaxIdFactory->create();
        $checkoutPaymentDetails['accountHolderName'] = $cardholder($quote);
        $checkoutPaymentDetails['accountHolderTaxId'] = $accountHolderTaxId();
        $checkoutPaymentDetails['accountHolderTaxIdCountry'] = $accountHolderTaxIdCountry();
        $checkoutPaymentDetails['billingAddress'] = $signifydAddress($billingAddress);

        return $checkoutPaymentDetails;
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
}
