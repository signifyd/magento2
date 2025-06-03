<?php
/**
 * Copyright 2017 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Signifyd\Connect\Observer\Debug;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Signifyd\Connect\Model\Registry;
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
    public $logger;

    /**
     * @var ConfigHelper
     */
    public $configHelper;

    /**
     * @var UrlInterface
     */
    public $url;

    /**
     * @var Registry
     */
    public $registry;

    /**
     * Order constructor.
     *
     * @param Debugger $logger
     * @param ConfigHelper $configHelper
     * @param UrlInterface $url
     * @param Registry $registry
     */
    public function __construct(
        Debugger $logger,
        ConfigHelper $configHelper,
        UrlInterface $url,
        Registry $registry
    ) {
        $this->logger = $logger;
        $this->configHelper = $configHelper;
        $this->url = $url;
        $this->registry = $registry;
    }

    /**
     * Execute method.
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        try {
            /** @var \Magento\Sales\Model\Order $order */
            $order = $observer->getEvent()->getOrder();

            $log = $this->configHelper->getConfigData('signifyd/logs/log', $order);

            // Log level 2 => debug
            if ($log == 2) {
                $state = $order->getState();
                $currentState = $order->getOrigData('state');
                $incrementId = $order->getIncrementId();

                $cronJob = $this->registry->getData('signifyd_cron_job_run');

                if (isset($cronJob)) {
                    $this->logger->debug("cron job current process: {$cronJob}");
                }

                $this->logger->debug("Order {$incrementId} state change from {$currentState} to {$state}");
                $this->logger->debug("Request URL: {$this->url->getCurrentUrl()}");

                list($debugBacktraceLog, $nonMagentoModules) = $this->getDebugBacktrace();

                if (empty($nonMagentoModules) == false) {
                    $nonMagentoModulesList = implode(', ', array_keys($nonMagentoModules));
                    $this->logger->debug("WARNING: non Magento modules found on backtrace: " .
                        $nonMagentoModulesList);
                }

                $debugBacktraceLog = implode("\n", $debugBacktraceLog);
                $this->logger->debug("Backtrace: \n{$debugBacktraceLog}\n\n");
            }
        } catch (\Exception $e) {
            $this->logger->debug("State debug failed: " . $e->getMessage());
        } catch (\Error $e) {
            $this->logger->debug("State debug failed: " . $e->getMessage());
        }
    }

    /**
     * Get debug backtrace method.
     *
     * @return array
     */
    public function getDebugBacktrace()
    {
        $debugBacktrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $debugBacktraceLog = [];
        $nonMagentoModules = [];

        foreach ($debugBacktrace as $i => $step) {
            $debugBacktraceLog[$i] = [];
            $function = '';

            if (isset($step['class']) && is_array($step['class'])) {
                $function .= $step['class'];
                list($vendor, $module, $class) = explode('\\', $step['class'], 3);

                if ($vendor != "Magento") {
                    $nonMagentoModules["{$vendor}\\{$module}"] = '';
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

        return [$debugBacktraceLog, $nonMagentoModules];
    }
}
