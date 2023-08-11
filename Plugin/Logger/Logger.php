<?php

namespace Signifyd\Connect\Plugin\Logger;

use Signifyd\Connect\Helper\ConfigHelper;
use Signifyd\Connect\Logger\Logger as SignifydLogger;

class Logger
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
     * @param ConfigHelper $configHelper
     */
    public function __construct(
        ConfigHelper $configHelper
    ) {
        $this->configHelper = $configHelper;
        $this->log = $this->configHelper->getConfigData('signifyd/logs/log');
    }

    /**
     * @param SignifydLogger $subject
     * @param callable $proceed
     * @param $level
     * @param $message
     * @param $context
     * @param $datetime
     * @return bool
     */
    public function aroundAddRecord(SignifydLogger $subject, callable $proceed, $level, $message, $context = [], $datetime = null)
    {
        $result = $proceed($level, $message, $context, $datetime);

        if (isset($context['entity'])) {
            $log = $this->configHelper->getConfigData('signifyd/logs/log', $context['entity']);
            unset($context['entity']);
        } else {
            $log = $this->log;
        }

        if ($log == false) {
            return false;
        }

        return $result;
    }
}
