<?php
/**
 * Copyright 2015 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Signifyd\Connect\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Psr\Log\LoggerInterface;

/**
 * Class LogHelper
 * Acts as a frontend for logging inside of the Signifyd plugin based on configured settings.
 * @package Signifyd\Connect\Helper
 */
class LogHelper
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $_logger;

    /**
     * @var bool
     */
    protected $_log;

    public function __construct(
        LoggerInterface $logger,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->_log = $scopeConfig->getValue('signifyd/logs/log');
        $this->_logger = $logger;
    }

    /**
     * Log requests to Signifyd service
     * @param string $message
     */
    public function request($message)
    {
        if (!$this->_log) {
            return;
        }
        $this->_logger->info($message);
    }

    /**
     * Log requests from Signifyd services (ie webhooks) and responses to requests
     * @param string $message
     */
    public function response($message)
    {
        if (!$this->_log) {
            return;
        }
        $this->_logger->info($message);
    }

    /**
     * Log errors occurring during operations.
     * @param string $message
     */
    public function error($message)
    {
        if (!$this->_log) {
            return;
        }
        $this->_logger->error($message);
    }

    /**
     * Trace out messages purely for debugging purposes.
     * @param string $message
     */
    public function debug($message)
    {
        if (!$this->_log) {
            return;
        }
        $this->_logger->info($message);
    }
}
