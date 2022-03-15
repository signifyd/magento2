<?php

namespace Signifyd\Connect\Plugin\Adyen\Payment\Helper;

use Adyen\Payment\Helper\Data as AdyenHelperData;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Signifyd\Connect\Helper\ConfigHelper;
use Magento\Store\Model\StoreManagerInterface;

class Data
{
    /**
     * @var null|int
     */
    protected $storeId = null;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var ConfigHelper
     */
    protected $configHelper;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * Data constructor.
     * @param ScopeConfigInterface $scopeConfig
     * @param ConfigHelper $configHelper
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ConfigHelper $configHelper,
        StoreManagerInterface $storeManager
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->configHelper = $configHelper;
        $this->storeManager = $storeManager;
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
        $storeId = $this->storeManager->getStore()->getId();

        $adyenProxyConfigEnabled = $this->scopeConfig->getValue(
            'signifyd/proxy/adyen_enable',
            'stores',
            $storeId
        );

        $adyenProxyEnabled = $adyenProxyConfigEnabled != 0;

        if ($adyenProxyEnabled && $this->configHelper->getEnabledByStoreId($storeId)) {
            $environmentReplace = $adyenProxyConfigEnabled == 2 ?
                ".staging.signifyd.com" : ".signifyd.com";

            $environmentUrl = $client->getConfig()->get('endpointCheckout');
            $environmentSignifydUrl = str_replace(".com", $environmentReplace, $environmentUrl);

            $client->getConfig()->set('endpointCheckout', $environmentSignifydUrl);
        }

        return $client;
    }
}
