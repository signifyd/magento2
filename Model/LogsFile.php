<?php

declare(strict_types=1);

namespace Signifyd\Connect\Model;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Io\File;
use Magento\Quote\Model\QuoteFactory;
use Magento\Quote\Model\ResourceModel\Quote as QuoteResource;
use Magento\Sales\Model\OrderFactory;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\ResourceModel\Logs\CollectionFactory as LogsCollectionFactory;
use Signifyd\Connect\Model\ResourceModel\Order as SignifydOrderResourceModel;
use Magento\Sales\Model\ResourceModel\Order\Status\History\CollectionFactory as HistoryCollectionFactory;

class LogsFile
{
    /**
     * @var DirectoryList
     */
    public $directoryList;

    /**
     * @var File
     */
    public $file;

    /**
     * @var SignifydOrderResourceModel
     */
    public $signifydOrderResourceModel;

    /**
     * @var OrderFactory
     */
    public $orderFactory;

    /**
     * @var LogsCollectionFactory
     */
    public $logsCollectionFactory;

    /**
     * @var Logger
     */
    public $logger;

    /**
     * @var QuoteFactory
     */
    public $quoteFactory;

    /**
     * @var QuoteResource
     */
    public $quoteResource;

    /**
     * @var HistoryCollectionFactory
     */
    public $historyCollectionFactory;

    /**
     * @param DirectoryList $directoryList
     * @param File $file
     * @param SignifydOrderResourceModel $signifydOrderResourceModel
     * @param OrderFactory $orderFactory
     * @param LogsCollectionFactory $logsCollectionFactory
     * @param Logger $logger
     * @param QuoteFactory $quoteFactory
     * @param QuoteResource $quoteResource
     * @param HistoryCollectionFactory $historyCollectionFactory
     */
    public function __construct(
        DirectoryList $directoryList,
        File $file,
        OrderFactory $orderFactory,
        SignifydOrderResourceModel $signifydOrderResourceModel,
        LogsCollectionFactory $logsCollectionFactory,
        Logger $logger,
        QuoteFactory $quoteFactory,
        QuoteResource $quoteResource,
        HistoryCollectionFactory $historyCollectionFactory
    ) {
        $this->directoryList = $directoryList;
        $this->file = $file;
        $this->signifydOrderResourceModel = $signifydOrderResourceModel;
        $this->orderFactory = $orderFactory;
        $this->logsCollectionFactory = $logsCollectionFactory;
        $this->logger = $logger;
        $this->quoteFactory = $quoteFactory;
        $this->quoteResource = $quoteResource;
        $this->historyCollectionFactory = $historyCollectionFactory;
    }

    /**
     * @return void
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    public function prepLogsDir()
    {
        $path = $this->directoryList->getPath('media') . '/signifyd_logs';

        try {
            $this->file->checkAndCreateFolder($path);
        } catch (\Exception $e) {
            return;
        }
    }

    /**
     * @param $orderId
     * @return string
     */
    public function createLogFile($orderId)
    {
        try {
            $this->prepLogsDir();

            /** @var \Magento\Sales\Model\Order $order */
            $order = $this->orderFactory->create();
            $this->signifydOrderResourceModel->load($order, $orderId);
            $quoteId = $order->getQuoteId();
            $fileName = $order->getIncrementId() . '.txt';
            $filePath = $this->directoryList->getPath('media') . '/signifyd_logs/' . $fileName;
            $fileData = '';

            $this->logger->info("Generating log for order " . $order->getIncrementId());

            /** @var \Signifyd\Connect\Model\ResourceModel\Logs\Collection $quoteLogsCollection */
            $quoteLogsCollection = $this->logsCollectionFactory->create()
                ->addFieldToFilter('quote_id', ['eq' => $quoteId])
                ->addFieldToFilter('order_id', ['null' => true]);

            if ($quoteLogsCollection->count() > 0) {
                /** @var \Signifyd\Connect\Model\Logs $log */
                foreach ($quoteLogsCollection as $quoteLog) {
                    $fileData .= '[' . strtoupper($quoteLog->getType()) . '] ' . '[' . $quoteLog->getCreatedAt() . '] ' .
                        $quoteLog->getEntry() . PHP_EOL;
                }
            }

            $orderLogsCollection = $this->logsCollectionFactory->create()
                ->addFieldToFilter('order_id', ['eq' => $orderId]);

            if ($orderLogsCollection->count() > 0) {
                /** @var \Signifyd\Connect\Model\Logs $log */
                foreach ($orderLogsCollection as $orderLog) {
                    $fileData .= '[' . strtoupper($orderLog->getType()) . '] ' . '[' . $orderLog->getCreatedAt() . '] ' .
                        $orderLog->getEntry() . PHP_EOL;
                }
            }

            if ($orderLogsCollection->count() === 0 && $quoteLogsCollection->count() === 0) {
                return 'No records found for order ' . $order->getIncrementId();
            }

            $quote = $this->quoteFactory->create();
            $this->quoteResource->load($quote, $order->getQuoteId());

            $fileData .= 'quote: ' . $quote->toJson() . PHP_EOL;
            $fileData .= 'quote_shipping_address: ' . $quote->getShippingAddress()->toJson() . PHP_EOL;
            $fileData .= 'quote_billing_address: ' . $quote->getBillingAddress()->toJson() . PHP_EOL;

            $fileData .= 'sales_order: ' . $order->toJson() . PHP_EOL;
            $fileData .= 'sales_order_shipping_address: ' . $order->getShippingAddress()->toJson() . PHP_EOL;
            $fileData .= 'sales_order_billing_address: ' . $order->getBillingAddress()->toJson() . PHP_EOL;

            $historyCollection = $this->historyCollectionFactory->create()
                ->addFieldToFilter('parent_id', ['eq' => $orderId]);

            foreach ($historyCollection as $history) {
                $fileData .= 'sales_order_status_history: ' . $history->toJson() . PHP_EOL;
            }

            $fh = new \SplFileObject($filePath, 'w');
            $fh->fwrite($fileData);

            return $fileName;
        } catch (\Exception $e) {
            $this->logger->error("failed to generate log: " . $e->getMessage());
            return $e->getMessage();
        } catch (\Error $e) {
            $this->logger->error("failed to generate log: " . $e->getMessage());
            return $e->getMessage();
        }
    }
}
