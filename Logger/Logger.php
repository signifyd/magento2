<?php

/**
 * Copyright 2015 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Signifyd\Connect\Logger;

use Signifyd\Connect\Helper\ConfigHelper;

class Logger extends \Monolog\Logger
{
    /**
     * @var bool
     */
    protected $log;

    /**
     * @var ConfigHelper
     */
    protected $configHelper;

    /**
     * Logger constructor.
     * @param string $name
     * @param array $handlers
     * @param array $processors
     * @param ConfigHelper $configHelper
     */
    public function __construct(
        $name,
        ConfigHelper $configHelper,
        array $handlers = [],
        array $processors = []
    ) {
        $this->configHelper = $configHelper;
        $this->log = $this->configHelper->getConfigData('signifyd/logs/log');

        return parent::__construct($name, $handlers, $processors);
    }

    /**
     * @param int $level
     * @param string $message
     * @param array $context
     * @return bool
     */
    public function addRecord($level, $message, array $context = []): bool
    {
        if (isset($context['entity'])) {
            $log = $this->configHelper->getConfigData('signifyd/logs/log', $context['entity']);
            unset($context['entity']);
        } else {
            $log = $this->log;
        }

        if ($log == false) {
            return false;
        }

        return parent::addRecord($level, $message, $context);
    }
}
