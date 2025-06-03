<?php

namespace Signifyd\Connect\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use \Magento\Store\Model\StoreManagerInterface;

class DeviceHelper
{
    /**
     * @var ScopeConfigInterface
     */
    public $scopeConfigInterface;

    /**
     * @var StoreManagerInterface
     */
    public $storeManager;

    /**
     * DeviceHelper constructor.
     * @param ScopeConfigInterface $scopeConfigInterface
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(ScopeConfigInterface $scopeConfigInterface, StoreManagerInterface $storeManager)
    {
        $this->scopeConfigInterface = $scopeConfigInterface;
        $this->storeManager = $storeManager;
    }

    /**
     * Check if device fingerprint functionality is enable
     * This configuration it is not present on system.xml, so it is not visible to admin
     * For this reason it will always only depend on default configuration and does not need to be checked by order
     * store configurations
     *
     * @return bool
     */
    public function isDeviceFingerprintEnabled()
    {
        return (bool) $this->scopeConfigInterface->getValue('signifyd/general/enable_device_fingerprint', 'store');
    }

    /**
     * Generate device fingerprint based on store URL and quoteId
     *
     * @param string $quoteId
     * @param null|string|bool|int|\Magento\Store\Api\Data\StoreInterface $storeId
     * @return string
     */
    public function generateFingerprint($quoteId, $storeId = null)
    {
        $baseUrl = $this->storeManager->getStore($storeId)->getBaseUrl();
        return 'M2' . base64_encode($baseUrl) . $quoteId;
    }
}
