<?php
/**
 * Copyright 2015 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Signifyd\Connect\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Signifyd\Core\SignifydSettings;

class SignifydSettingsMagento extends SignifydSettings
{
    /**
     * @var bool Is the plugin enabled?
     */
    public $enabled = true;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        LogHelper $logger
    ) {
        $this->enabled = $scopeConfig->isSetFlag('signifyd/general/enabled');
        $this->apiKey = $scopeConfig->getValue('signifyd/general/key');

        $this->logInfo = true;
        $this->logError = true;
        $this->loggerError = function($message) use ($logger)
        {
            $logger->error("API Error: " . $message);
        };
        $this->apiAddress = "https://api.signifyd.com/v2";
    }
}
