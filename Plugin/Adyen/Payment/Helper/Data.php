<?php

namespace Signifyd\Connect\Plugin\Adyen\Payment\Helper;

use Adyen\Payment\Helper\Data as AdyenHelperData;
use Magento\Framework\App\Config\ScopeConfigInterface;

class Data
{
    /**
     *
     * @var string[]
     */
    protected $endpoints = [
        \Adyen\Environment::TEST => 'https://checkout-test.adyen.staging.signifyd.com/checkout',
        \Adyen\Environment::LIVE => 'https://checkout-test.adyen.com/checkout'
    ];

    /**
     * @var null|int
     */
    protected $storeId = null;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * Data constructor.
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @param AdyenHelperData $subject
     * @param null $storeId
     * @param null $apiKey
     */
    public function beforeInitializeAdyenClient(AdyenHelperData $subject, $storeId = null, $apiKey = null)
    {
        $this->storeId = $storeId;
    }

    /**
     * @param AdyenHelperData $subject
     * @param $client
     * @return mixed
     */
    public function afterInitializeAdyenClient(AdyenHelperData $subject, $client)
    {
        $adyenProxyEnabled = $this->scopeConfig->isSetFlag(
            'signifyd/proxy/adyen_enable',
            'stores', $this->storeId
        );

        $isEnable = $this->scopeConfig->isSetFlag('signifyd/general/enabled', 'stores', $this->storeId);

        if ($adyenProxyEnabled && $isEnable) {
            $environment = $subject->isDemoMode($this->storeId) ? \Adyen\Environment::TEST : \Adyen\Environment::LIVE;
            $client->getConfig()->set('endpointCheckout', $this->endpoints[$environment]);
        }

        return $client;
    }
}
