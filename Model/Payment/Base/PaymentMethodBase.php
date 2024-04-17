<?php

namespace Signifyd\Connect\Model\Payment\Base;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use Signifyd\Connect\Api\PaymentMethodMappingInterface;
use Signifyd\Connect\Helper\ConfigHelper;
use Signifyd\Connect\Logger\Logger;

abstract class PaymentMethodBase implements PaymentMethodMappingInterface
{
    /**
     * @var JsonSerializer
     */
    public $jsonSerializer;

    /**
     * List of payment methods codes
     * @var array
     */
    public $allowedMethods = [];

    /**
     * @var Logger
     */
    public $logger;

    /**
     * @var ConfigHelper
     */
    public $configHelper;

    /**
     * @var ScopeConfigInterface
     */
    public $scopeConfigInterface;

    /**
     * @param JsonSerializer $jsonSerializer
     * @param Logger $logger
     * @param ConfigHelper $configHelper
     * @param ScopeConfigInterface $scopeConfigInterface
     */
    public function __construct(
        JsonSerializer $jsonSerializer,
        Logger $logger,
        ConfigHelper $configHelper,
        ScopeConfigInterface $scopeConfigInterface
    ) {
        $this->jsonSerializer = $jsonSerializer;
        $this->logger = $logger;
        $this->configHelper = $configHelper;
        $this->scopeConfigInterface = $scopeConfigInterface;
    }

    /**
     * @param Order|Quote $entity
     * @return mixed
     */
    public function getData($entity)
    {
        if ($entity instanceof Order && $entity->isEmpty() === false) {
            $data = $this->getPaymentMethodFromOrder($entity);
        } elseif ($entity instanceof Quote && $entity->isEmpty() === false) {
            $data = $this->getPaymentMethodFromQuote($entity);
        } else {
            return 'CREDIT_CARD';
        }

        return $data;
    }

    /**
     * @param $paymentMethod
     * @return int|string
     */
    public function makePaymentMethod($paymentMethod)
    {
        $allowMethodsJson = $this->scopeConfigInterface->getValue('signifyd/general/payment_methods_config');

        try {
            $allowMethods = $this->jsonSerializer->unserialize($allowMethodsJson);
        } catch (\InvalidArgumentException $e) {
            return $paymentMethod;
        }

        foreach ($allowMethods as $i => $allowMethod) {
            if (in_array($paymentMethod, $allowMethod)) {
                return $i;
            }
        }

        return $paymentMethod;
    }
}
