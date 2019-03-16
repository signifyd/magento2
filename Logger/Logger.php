<?php

/**
 * Copyright 2015 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Signifyd\Connect\Logger;

use Magento\Framework\App\Config\ScopeConfigInterface;

class Logger extends \Monolog\Logger
{
    /**
     * @var bool
     */
    protected $log;

    /**
     * Logger constructor.
     * @param string $name
     * @param array $handlers
     * @param array $processors
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        $name,
        array $handlers = array(),
        array $processors = array(),
        ScopeConfigInterface $scopeConfig
    ) {
        $this->log = $scopeConfig->getValue('signifyd/logs/log', 'stores');

        return parent::__construct($name, $handlers, $processors);
    }

    /**
     * @param int $level
     * @param string $message
     * @param array $context
     * @return bool
     */
    public function addRecord($level, $message, array $context = array())
    {
        if ($this->log == false) {
            return false;
        }

        return parent::addRecord($level, $message, $context);
    }
}