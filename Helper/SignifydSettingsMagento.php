<?php
/**
 * Copyright © 2015 SIGNIFYD Inc. All rights reserved.
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
        ScopeConfigInterface $scopeConfig
    ) {
        $this->enabled = $scopeConfig->isSetFlag('signifyd/general/enabled');
        $this->apiKey = $scopeConfig->getValue('signifyd/general/key');

        $this->logInfo = true;
        $this->apiAddress = $scopeConfig->getValue('signifyd/general/url');
    }
}
