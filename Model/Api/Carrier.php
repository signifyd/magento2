<?php

namespace Signifyd\Connect\Model\Api;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;

class Carrier
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
     * Construct a new Carrier object
     * @param $shippingMethod
     * @return int|string|null
     */
    public function __invoke($shippingMethod)
    {
        if (isset($shippingMethod) === false) {
            return null;
        }

        if (is_string($shippingMethod)) {
            $shippingMethodArray = explode('_', $shippingMethod);

            if (count($shippingMethodArray) < 2) {
                return null;
            }

            $shippingCarrier = $shippingMethodArray[0];
        } else {
            $shippingCarrier = $shippingMethod->getCarrierCode();
        }

        $allowMethodsJson = $this->scopeConfigInterface->getValue('signifyd/general/shipper_config');

        try {
            $allowMethods = $this->jsonSerializer->unserialize($allowMethodsJson);
        } catch (\InvalidArgumentException $e) {
            return null;
        }

        foreach ($allowMethods as $i => $allowMethod) {
            if (in_array($shippingCarrier, $allowMethod)) {
                return $i;
            }
        }

        return null;
    }
}