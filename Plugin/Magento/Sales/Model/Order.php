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
    )
    {
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

                $this->logger->debug("Order {$subject->getIncrementId()} state change from {$currentState} to {$state}");
                $this->logger->debug("Request URL: {$this->url->getCurrentUrl()}");

                $debugBacktrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
                $debugBacktraceLog = [];
                $nonMagentoModules = [];

                foreach ($debugBacktrace as $i => $step) {
                    $debugBacktraceLog[$i] = [];
                    $function = '';

                    if (isset($step['class'])) {
                        $function .= $step['class'];

                        if ($step['class'] != 'Signifyd\Connect\Plugin\Magento\Sales\Model\Order') {
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
        } catch (\Exception $e) {
            $this->logger('Exception logging order state change: ' . $e->getMessage());
        }

        return array($state);
    }
}