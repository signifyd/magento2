<?php

namespace Signifyd\Connect\Plugin\Magento\Sales\Model;

use Signifyd\Connect\Logger\Debugger;
use Signifyd\Connect\Helper\ConfigHelper;
use Magento\Framework\UrlInterface;

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
     * @var UrlInterface
     */
    protected $url;

    /**
     * Order constructor.
     * @param Debugger $logger
     * @param ConfigHelper $configHelper
     * @param UrlInterface $url
     */
    public function __construct(
        Debugger $logger,
        ConfigHelper $configHelper,
        UrlInterface $url
    ) {
        $this->logger = $logger;
        $this->configHelper = $configHelper;
        $this->url = $url;
    }

    /**
     * @param \Magento\Sales\Model\Order $subject
     * @param $state
     * @return array
     */
    public function beforeSetState(\Magento\Sales\Model\Order $subject, $state)
    {
        try {
            $log = $this->configHelper->getConfigData('signifyd/logs/log', $subject);

            // Log level 2 => debug
            if ($log == 2) {
                $currentState = $subject->getState();
                $incrementId = $subject->getIncrementId();

                $this->logger->debug("setState on order {$incrementId} state change from {$currentState} to {$state}");
                $this->logger->debug("Request URL: {$this->url->getCurrentUrl()}");
            }
        } catch (\Exception $e) {
            $this->logger->debug('Exception logging order state change: ' . $e->getMessage());
        }

        return [$state];
    }
}
