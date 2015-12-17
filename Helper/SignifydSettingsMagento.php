<?php

namespace Signifyd\Connect\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Signifyd\Core\SignifydSettings;

class SignifydSettingsMagento extends SignifydSettings
{
    public function __construct(
        ScopeConfigInterface $scopeConfig
    ) {
        $this->apiKey = $scopeConfig->getValue('signifyd/general/key');

        $this->logInfo = true;
        $this->apiAddress = $scopeConfig->getValue('signifyd/general/url');
    }
}
