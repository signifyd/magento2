<?php
/**
 * Copyright 2015 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Signifyd\Connect\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * Class LogHelper
 * Acts as a frontend for logging inside of the Signifyd plugin based on configured settings.
 * @package Signifyd\Connect\Helper
 */
class LogHelper
{
    /**
     * @var \Signifyd\Connect\Logger\Logger
     */
    protected $logger;

    /**
     * @var bool
     */
    protected $log;

    /**
     * LogHelper constructor.
     * @param \Signifyd\Connect\Logger\Logger $logger
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        \Signifyd\Connect\Logger\Logger $logger,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->log = $scopeConfig->getValue('signifyd/logs/log', 'stores');
        $this->logger = $logger;
    }

    /**
     * Log requests to Signifyd service
     * @param string $message
     */
    public function request($message)
    {
        if (!$this->log) {
            return;
        }
        $this->logger->info($message);
    }

    /**
     * Log requests from Signifyd services (ie webhooks) and responses to requests
     * @param string $message
     */
    public function response($message)
    {
        if (!$this->log) {
            return;
        }
        $this->logger->info($message);
    }

    /**
     * Log errors occurring during operations.
     * @param string $message
     */
    public function error($message)
    {
        if (!$this->log) {
            return;
        }
        $this->logger->error($message);
    }

    /**
     * Trace out messages purely for debugging purposes.
     * @param string $message
     */
    public function debug($message)
    {
        if (!$this->log) {
            return;
        }
        $this->logger->info($message);
    }
}
