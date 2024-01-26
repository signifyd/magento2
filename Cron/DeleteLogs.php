<?php

namespace Signifyd\Connect\Cron;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\ResourceModel\Logs as LogsResourceModel;
use Signifyd\Connect\Model\LogsFile;
use Signifyd\Connect\Model\ResourceModel\Logs\CollectionFactory as LogsCollectionFactory;
use Magento\Framework\Filesystem\Driver\File as DriverFile;

class DeleteLogs
{
    /**
     * @var ScopeConfigInterface
     */
    public $scopeConfigInterface;

    /**
     * @var LogsCollectionFactory
     */
    public $logsCollectionFactory;

    /**
     * @var LogsResourceModel
     */
    public $logsResourceModel;

    /**
     * @var DateTime
     */
    public $dateTime;

    /**
     * @var Logger
     */
    public $logger;

    /**
     * @var DirectoryList
     */
    public $directoryList;

    /**
     * @var DriverFile
     */
    public $driverFile;

    /**
     * @var LogsFile
     */
    public $logsFile;

    /**
     * @param ScopeConfigInterface $scopeConfigInterface
     * @param LogsCollectionFactory $logsCollectionFactory
     * @param LogsResourceModel $logsResourceModel
     * @param DateTime $dateTime
     * @param Logger $logger
     * @param DirectoryList $directoryList
     * @param DriverFile $driverFile
     * @param LogsFile $logsFile
     */
    public function __construct(
        ScopeConfigInterface $scopeConfigInterface,
        LogsCollectionFactory $logsCollectionFactory,
        LogsResourceModel $logsResourceModel,
        DateTime $dateTime,
        Logger $logger,
        DirectoryList $directoryList,
        DriverFile $driverFile,
        LogsFile $logsFile
    ) {
        $this->scopeConfigInterface = $scopeConfigInterface;
        $this->logsCollectionFactory = $logsCollectionFactory;
        $this->logsResourceModel = $logsResourceModel;
        $this->dateTime = $dateTime;
        $this->logger = $logger;
        $this->directoryList = $directoryList;
        $this->driverFile = $driverFile;
        $this->logsFile = $logsFile;
    }

    public function execute()
    {
        $logsRetentionPeriod = $this->scopeConfigInterface->getValue("signifyd/advanced/logs_retention_period");
        $fromDate = $this->dateTime->gmtDate('Y-m-d H:i:s', "-{$logsRetentionPeriod} days");

        $logsCollection = $this->logsCollectionFactory->create();
        $logsCollection->addFieldToFilter('created_at', ['lt' => $fromDate]);

        foreach ($logsCollection as $log) {
            try {
                $this->logsResourceModel->delete($log);
            } catch (\Exception $e) {
                $this->logger->info("CRON: Failed to delete log " . $log->getLogsId());
            }
        }

        $this->logsFile->prepLogsDir();
        $path = $this->directoryList->getPath('media') . '/signifyd_logs';

        try {
            $this->driverFile->deleteDirectory($path);
        } catch (\Exception $e) {
            $this->logger->info("CRON: Failed to delete log files: " . $e->getMessage());
        }
    }
}
