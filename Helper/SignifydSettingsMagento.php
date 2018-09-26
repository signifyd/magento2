<?php
/**
 * Copyright 2015 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Signifyd\Connect\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Signifyd\Core\SignifydSettings;

class SignifydSettingsMagento extends SignifydSettings
{
    public $logInfo = true;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        LogHelper $logger,
        DirectoryList $directoryList
    ) {
        $this->loggerError = function($message) use ($logger)
        {
            $logger->error("API Error: " . $message);
        };
        $this->logFileLocation = $directoryList->getPath('log');
    }
}
