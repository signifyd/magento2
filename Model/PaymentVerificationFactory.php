<?php

namespace Signifyd\Connect\Model;

use Magento\Framework\ObjectManagerInterface;
use Signifyd\Connect\Api\PaymentVerificationInterface;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Signifyd\Connect\Helper\ConfigHelper;

/**
 * Creates verification service for provided payment method, or PaymentVerificationInterface::class
 * if payment method does not support AVS, CVV verifications.
 */
class PaymentVerificationFactory
{
    /**
     * @var ConfigInterface
     */
    protected $config;

    /**
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @var PaymentVerificationInterface
     */
    protected $avsDefaultAdapter;

    /**
     * @var PaymentVerificationInterface
     */
    protected $cvvDefaultAdapter;

    /**
     * @var PaymentVerificationInterface
     */
    protected $cardholderDefaultAdapter;

    /**
     * @var PaymentVerificationInterface
     */
    protected $last4DefaultAdapter;

    /**
     * @var PaymentVerificationInterface
     */
    protected $expMonthDefaultAdapter;

    /**
     * @var PaymentVerificationInterface
     */
    protected $expYearDefaultAdapter;

    /**
     * @var PaymentVerificationInterface
     */
    protected $binDefaultAdapter;

    /**
     * @var PaymentVerificationInterface
     */
    protected $transactionIdDefaultAdapter;

    /**
     * @var ConfigHelper
     */
    protected $configHelper;

    /**
     * @param ObjectManagerInterface $objectManager
     * @param ConfigInterface|Config $config
     * @param PaymentVerificationInterface $avsDefaultAdapter
     * @param PaymentVerificationInterface $cvvDefaultAdapter
     * @param PaymentVerificationInterface $cardholderDefaultAdapter
     * @param PaymentVerificationInterface $last4DefaultAdapter
     * @param PaymentVerificationInterface $expMonthDefaultAdapter
     * @param PaymentVerificationInterface $expYearDefaultAdapter
     * @param PaymentVerificationInterface $binDefaultAdapter
     * @param ConfigHelper $configHelper
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        ConfigInterface $config,
        PaymentVerificationInterface $avsDefaultAdapter,
        PaymentVerificationInterface $cvvDefaultAdapter,
        PaymentVerificationInterface $cardholderDefaultAdapter,
        PaymentVerificationInterface $last4DefaultAdapter,
        PaymentVerificationInterface $expMonthDefaultAdapter,
        PaymentVerificationInterface $expYearDefaultAdapter,
        PaymentVerificationInterface $binDefaultAdapter,
        PaymentVerificationInterface $transactionIdDefaultAdapter,
        ConfigHelper $configHelper
    ) {
        $this->config = $config;
        $this->objectManager = $objectManager;
        $this->avsDefaultAdapter = $avsDefaultAdapter;
        $this->cvvDefaultAdapter = $cvvDefaultAdapter;
        $this->cardholderDefaultAdapter = $cardholderDefaultAdapter;
        $this->last4DefaultAdapter = $last4DefaultAdapter;
        $this->expMonthDefaultAdapter = $expMonthDefaultAdapter;
        $this->expYearDefaultAdapter = $expYearDefaultAdapter;
        $this->binDefaultAdapter = $binDefaultAdapter;
        $this->transactionIdDefaultAdapter = $transactionIdDefaultAdapter;
        $this->configHelper = $configHelper;
    }

    /**
     * Creates instance of CVV code verification.
     * Exception will be thrown if mapper does not implement PaymentVerificationInterface.
     *
     * @param string $paymentCode
     * @return PaymentVerificationInterface
     * @throws \Exception
     */
    public function createPaymentCvv($paymentCode)
    {
        return $this->create($this->cvvDefaultAdapter, $paymentCode, 'signifyd_cvv_ems_adapter');
    }

    /**
     * Creates instance of AVS code verification.
     * Exception will be thrown if mapper does not implement PaymentVerificationInterface.
     *
     * @param string $paymentCode
     * @return PaymentVerificationInterface
     * @throws \Exception
     */
    public function createPaymentAvs($paymentCode)
    {
        return $this->create($this->avsDefaultAdapter, $paymentCode, 'signifyd_avs_ems_adapter');
    }

    /**
     * Creates instance of cardholder mapper.
     * Exception will be thrown if mapper does not implement PaymentVerificationInterface.
     *
     * @param string $paymentCode
     * @return PaymentVerificationInterface
     * @throws \Exception
     */
    public function createPaymentCardholder($paymentCode)
    {
        return $this->create($this->cardholderDefaultAdapter, $paymentCode, 'signifyd_cardholder_adapter');
    }

    /**
     * Creates instance of last4 mapper.
     * Exception will be thrown if mapper does not implement PaymentVerificationInterface.
     *
     * @param string $paymentCode
     * @return PaymentVerificationInterface
     * @throws \Exception
     */
    public function createPaymentLast4($paymentCode)
    {
        return $this->create($this->last4DefaultAdapter, $paymentCode, 'signifyd_last4_adapter');
    }

    /**
     * Creates instance of expiration month mapper.
     * Exception will be thrown if mapper does not implement PaymentVerificationInterface.
     *
     * @param string $paymentCode
     * @return PaymentVerificationInterface
     * @throws \Exception
     */
    public function createPaymentExpMonth($paymentCode)
    {
        return $this->create($this->expMonthDefaultAdapter, $paymentCode, 'signifyd_exp_month_adapter');
    }

    /**
     * Creates instance of expiration year mapper.
     * Exception will be thrown if mapper does not implement PaymentVerificationInterface.
     *
     * @param string $paymentCode
     * @return PaymentVerificationInterface
     * @throws \Exception
     */
    public function createPaymentExpYear($paymentCode)
    {
        return $this->create($this->expYearDefaultAdapter, $paymentCode, 'signifyd_exp_year_adapter');
    }

    /**
     * Creates instance of bin mapper.
     * Exception will be thrown if mapper does not implement PaymentVerificationInterface.
     *
     * @param string $paymentCode
     * @return PaymentVerificationInterface
     * @throws \Exception
     */
    public function createPaymentBin($paymentCode)
    {
        return $this->create($this->binDefaultAdapter, $paymentCode, 'signifyd_bin_adapter');
    }

    /**
     * Creates instance of bin mapper.
     * Exception will be thrown if mapper does not implement PaymentVerificationInterface.
     *
     * @param string $paymentCode
     * @return PaymentVerificationInterface
     * @throws \Exception
     */
    public function createPaymentTransactionId($paymentCode)
    {
        return $this->create($this->transactionIdDefaultAdapter, $paymentCode, 'signifyd_transaction_id_adapter');
    }

    /**
     * Creates instance of PaymentVerificationInterface.
     * Default implementation will be returned if payment method does not implement PaymentVerificationInterface.
     *
     * Will search for payment verification class on payment/[method]/[config_key] configuration path first
     * If not found will try for signifyd/payment/[method]/[config_key]
     * We keep looking on payment/[method]/[config_key] because this is the path on 3.5.1 and older versions
     *
     * @param PaymentVerificationInterface $defaultAdapter
     * @param string $paymentCode
     * @param string $configKey
     * @return PaymentVerificationInterface
     * @throws ConfigurationMismatchException If payment verification instance
     * does not implement PaymentVerificationInterface.
     */
    private function create(PaymentVerificationInterface $defaultAdapter, $paymentCode, $configKey)
    {
        $this->config->setMethodCode($paymentCode);
        $verificationClass = $this->config->getValue($configKey);

        if (empty($verificationClass)) {
            $verificationClass = $this->configHelper->getConfigData("signifyd/payment/{$paymentCode}/{$configKey}");
        }

        if (empty($verificationClass)) {
            return $defaultAdapter;
        }

        $mapper = $this->objectManager->create($verificationClass);
        if (!$mapper instanceof PaymentVerificationInterface) {
            throw new LocalizedException(
                __('Signifyd_Connect: %1 must implement %2', $verificationClass, PaymentVerificationInterface::class)
            );
        }
        return $mapper;
    }
}
