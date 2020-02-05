<?php
/**
 * Copyright 2017 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Signifyd\Connect\Observer\Debug;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\UrlInterface;
use Signifyd\Connect\Logger\Debugger;
use Signifyd\Connect\Helper\ConfigHelper;

/**
 * Observer for purchase event. Sends order data to Signifyd service
 */
class Order implements ObserverInterface
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

    public function execute(Observer $observer)
    {
        try {
            /** @var $order \Magento\Sales\Model\Order */
            $order = $observer->getEvent()->getOrder();

            $log = $this->configHelper->getConfigData('signifyd/logs/log', $order);

            // Log level 2 => debug
            if ($log == 2) {
                $state = $order->getState();
                $currentState = $order->getOrigData('state');

                if ($state <> $currentState) {
                    $incrementId = $order->getIncrementId();

                    $this->logger->debug("Order {$incrementId} state change from {$currentState} to {$state}");
                    $this->logger->debug("Request URL: {$this->url->getCurrentUrl()}");

                    $debugBacktrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
                    $debugBacktraceLog = [];
                    $nonMagentoModules = [];

                    foreach ($debugBacktrace as $i => $step) {
                        $debugBacktraceLog[$i] = [];
                        $function = '';

                        if (isset($step['class'])) {
                            $function .= $step['class'];

                            if ($step['class'] != \Signifyd\Connect\Plugin\Magento\Sales\Model\Order::class) {
                                list($vendor, $module, $class) = explode('\\', $step['class'], 3);

                                if ($vendor != "Magento") {
                                    $nonMagentoModules["{$vendor}\\{$module}"] = '';
                                }
                            }
                        }

                        if (isset($step['type'])) {
                            $function .= $step['type'];
                        }

                        if (isset($step['function'])) {
                            $function .= $step['function'];
                        }

                        $debugBacktraceLog[$i][] = "\t[{$i}] {$function}";

                        $file = isset($step['file']) ? str_replace(BP, '', $step['file']) : false;

                        if ($file !== false) {
                            $debugBacktraceLog[$i][] = "line {$step['line']} on {$file}";
                        }

                        $debugBacktraceLog[$i] = implode(', ', $debugBacktraceLog[$i]);
                    }

                    if (empty($nonMagentoModules) == false) {
                        $nonMagentoModulesList = implode(', ', array_keys($nonMagentoModules));
                        $this->logger->debug("WARNING: non Magento modules found on backtrace: {$nonMagentoModulesList}");
                    }

                    $debugBacktraceLog = implode("\n", $debugBacktraceLog);
                    $this->logger->debug("Backtrace: \n{$debugBacktraceLog}\n\n");
                }
            }
        } catch (\Exception $e) {
        }
    }
}