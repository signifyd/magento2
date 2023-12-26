<?php

namespace Signifyd\Connect\Plugin\Logger;

use Signifyd\Connect\Helper\ConfigHelper;
use Signifyd\Connect\Logger\Logger as SignifydLogger;
use Signifyd\Connect\Model\LogsFactory;
use Signifyd\Connect\Model\ResourceModel\Logs as LogsResourceModel;

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
     * @var LogsFactory
     */
    protected $logsFactory;

    /**
     * @var LogsResourceModel
     */
    protected $logsResourceModel;

    /**
     * @param ConfigHelper $configHelper
     * @param LogsFactory $logsFactory
     * @param LogsResourceModel $logsResourceModel
     */
    public function __construct(
        ConfigHelper $configHelper,
        LogsFactory $logsFactory,
        LogsResourceModel $logsResourceModel
    ) {
        $this->configHelper = $configHelper;
        $this->logsFactory = $logsFactory;
        $this->logsResourceModel = $logsResourceModel;
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

            try {
                /** @var \Signifyd\Connect\Model\Logs $modelLogs */
                $modelLogs = $this->getSignifydLogModel($context['entity']);
                $type = $level === 200 ? 'Info' : 'Debug';
                $modelLogs->setType($type);
                $modelLogs->setEntry($message);
                $this->logsResourceModel->save($modelLogs);
            } catch (\Error $e) {

            } catch (\Exception $e) {

            }
            unset($context['entity']);
        } else {
            $log = $this->log;
        }

        if ($log == false) {
            return false;
        }

        return $result;
    }

    public function getSignifydLogModel($entity)
    {
        /** @var \Signifyd\Connect\Model\Logs $logs */
        $logs = $this->logsFactory->create();

        if ($entity instanceof \Signifyd\Connect\Model\Casedata && $entity->isEmpty() == false) {
            $orderId = $entity->getOrderId();
            $quoteId = $entity->getQuoteId();

            if (isset($orderId)) {
                $logs->setOrderId($orderId);
            }

            if (isset($quoteId)) {
                $logs->setQuoteId($quoteId);
            }
        } elseif ($entity instanceof \Magento\Sales\Model\Order && $entity->isEmpty() == false) {
            $orderId = $entity->getId();
            $logs->setOrderId($orderId);
        } elseif ($entity instanceof \Magento\Quote\Model\Quote && $entity->isEmpty() == false) {
            $quoteId = $entity->getId();
            $logs->setQuoteId($quoteId);
        }

        return $logs;
    }
}
