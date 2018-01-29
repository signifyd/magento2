<?php

namespace Signifyd\Connect\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use \Magento\Store\Model\StoreManagerInterface;

class DeviceHelper
{
    protected $scopeConfigInterface;

    protected $storeManager;

    public function __construct(ScopeConfigInterface $scopeConfigInterface, StoreManagerInterface $storeManager)
    {
        $this->scopeConfigInterface = $scopeConfigInterface;
        $this->storeManager = $storeManager;
    }

    public function isDeviceFingerprintEnabled()
    {
        return (bool) $this->scopeConfigInterface->getValue('signifyd/general/enable_device_fingerprint', 'store');
    }

    public function generateFingerprint($quoteId)
    {
        $baseUrl = $this->storeManager->getStore()->getBaseUrl();
        return 'M2' . base64_encode($baseUrl) . $quoteId;
    }
}