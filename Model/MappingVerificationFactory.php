<?php

namespace Signifyd\Connect\Model;

use Magento\Framework\ObjectManagerInterface;
use Signifyd\Connect\Api\PaymentMethodMappingInterface;
use Magento\Payment\Gateway\ConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Signifyd\Connect\Helper\ConfigHelper;

class MappingVerificationFactory
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
     * @var PaymentMethodMappingInterface
     */
    protected $paymentMethodDefaultAdapter;

    /**
     * @var ConfigHelper
     */
    protected $configHelper;

    /**
     * @param ObjectManagerInterface $objectManager
     * @param ConfigInterface $config
     * @param PaymentMethodMappingInterface $paymentMethodDefaultAdapter
     * @param ConfigHelper $configHelper
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        ConfigInterface $config,
        PaymentMethodMappingInterface $paymentMethodDefaultAdapter,
        ConfigHelper $configHelper
    ) {
        $this->config = $config;
        $this->objectManager = $objectManager;
        $this->paymentMethodDefaultAdapter = $paymentMethodDefaultAdapter;
        $this->configHelper = $configHelper;
    }

    public function createPaymentMethod($paymentCode)
    {
        return $this->create($this->paymentMethodDefaultAdapter, $paymentCode, 'signifyd_payment_method_adapter');
    }

    /**
     * @param PaymentMethodMappingInterface $defaultAdapter
     * @param $paymentCode
     * @param $configKey
     * @return PaymentMethodMappingInterface
     * @throws LocalizedException If payment verification instance
     *  does not implement PaymentMethodMappingInterface.
     */
    private function create(PaymentMethodMappingInterface $defaultAdapter, $paymentCode, $configKey)
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
        if (!$mapper instanceof PaymentMethodMappingInterface) {
            throw new LocalizedException(
                __('Signifyd_Connect: %1 must implement %2', $verificationClass, PaymentMethodMappingInterface::class)
            );
        }
        return $mapper;
    }
}
