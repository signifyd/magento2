<?php

namespace Signifyd\Connect\Model\Api;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;

class PaymentMethod
{
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfigInterface;

    /**
     * @var JsonSerializer
     */
    protected $jsonSerializer;

    /**
     * @param ScopeConfigInterface $scopeConfigInterface
     * @param JsonSerializer $jsonSerializer
     */
    public function __construct(
        ScopeConfigInterface $scopeConfigInterface,
        JsonSerializer $jsonSerializer
    ) {
        $this->scopeConfigInterface = $scopeConfigInterface;
        $this->jsonSerializer = $jsonSerializer;
    }

    /**
     * Construct a new PaymentMethod object
     * @param $paymentMethod
     * @return int|mixed|string
     */
    public function __invoke($paymentMethod)
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