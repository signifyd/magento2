<?php

namespace Signifyd\Connect\Plugin\Magento\Sales\Model;

use Signifyd\Connect\Logger\Debugger;
use Signifyd\Connect\Helper\ConfigHelper;

class Order
{
    /**
     * @var \Signifyd\Connect\Logger\Debugger
     */
    protected $logger;

    /**
     * @var ConfigHelper
     */
    protected $configHelper;

    /**
     * Order constructor.
     * @param Debugger $logger
     * @param ConfigHelper $configHelper
     */
    public function __construct(
        Debugger $logger,
        ConfigHelper $configHelper
    )
    {
        $this->logger = $logger;
        $this->configHelper = $configHelper;
    }

    /**
     * @param \Magento\Sales\Model\Order $subject
     * @param $state
     * @return array
     */
    public function beforeSetState(\Magento\Sales\Model\Order $subject, $state)
    {
        $log = $this->configHelper->getConfigData('signifyd/logs/log', $subject);

        // Log level 2 => debug
        if ($log == 2) {
            $currentState = $subject->getState();

            $this->logger->debug("Order state change from {$currentState} to {$state}");
        }

        return array($state);
    }
}