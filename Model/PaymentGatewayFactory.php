<?php

namespace Signifyd\Connect\Model;

use Magento\Framework\ObjectManagerInterface;

class PaymentGatewayFactory
{
    /**
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @var array
     */
    protected $gateways = [];

    /**
     * PaymentGatewayFactory constructor.
     * @param ObjectManagerInterface $objectManager
     */
    public function __construct(
        ObjectManagerInterface $objectManager
    ) {
        $this->objectManager = $objectManager;
    }

    /**
     * @param $gatewayIntegrationSettings
     * @return false|mixed
     */
    public function get($gatewayIntegrationSettings)
    {
        if (isset($gatewayIntegrationSettings['gateway']) === false ||
            isset($gatewayIntegrationSettings['params']) === false) {
            return false;
        }

        if (isset($this->gateways[$gatewayIntegrationSettings['gateway']])) {
            return $this->gateways[$gatewayIntegrationSettings['gateway']];
        }

        $gatewayObject = $this->create($gatewayIntegrationSettings);

        return $gatewayObject;
    }

    /**
     * @param $gatewayIntegrationSettings
     * @return false|mixed
     */
    public function create($gatewayIntegrationSettings)
    {
        if (isset($gatewayIntegrationSettings['gateway']) === false ||
            isset($gatewayIntegrationSettings['params']) === false) {
            return false;
        }

        $gatewayClass = $this->getGatewayClass($gatewayIntegrationSettings);

        if ($gatewayClass === false) {
            return false;
        }

        return $this->objectManager->create($gatewayClass, ['params' => $gatewayIntegrationSettings['params']]);
    }

    /**
     * @param $gatewayIntegrationSettings
     * @return false|string
     */
    public function getGatewayClass($gatewayIntegrationSettings)
    {
        if (isset($gatewayIntegrationSettings['gateway']) === false) {
            return false;
        }

        $gatewayClass = $gatewayIntegrationSettings['gateway'];

        if (class_exists($gatewayClass)) {
            return $gatewayClass;
        }

        return false;
    }
}
