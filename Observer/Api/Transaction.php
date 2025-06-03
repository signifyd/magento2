<?php

namespace Signifyd\Connect\Observer\Api;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Signifyd\Connect\Helper\ConfigHelper;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\Api\CaseData\PreAuth\ProcessTransactionFactory;

class Transaction implements ObserverInterface
{
    /**
     * @var Logger
     */
    public $logger;

    /**
     * @var ConfigHelper
     */
    public $configHelper;

    /**
     * @var ProcessTransactionFactory
     */
    public $processTransactionFactory;

    /**
     * Transaction constructor.
     *
     * @param Logger $logger
     * @param ConfigHelper $configHelper
     * @param ProcessTransactionFactory $processTransactionFactory
     */
    public function __construct(
        Logger $logger,
        ConfigHelper $configHelper,
        ProcessTransactionFactory $processTransactionFactory
    ) {
        $this->logger = $logger;
        $this->configHelper = $configHelper;
        $this->processTransactionFactory = $processTransactionFactory;
    }

    /**
     * Execute method.
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        if ($this->configHelper->isEnabled()) {
            try {
                /** @var \Magento\Sales\Model\Order $order */
                $order = $observer->getEvent()->getOrder();

                $processTransaction = $this->processTransactionFactory->create();
                $processTransaction($order);
            } catch (\Exception $e) {
                $this->logger->debug($e->getMessage());
            }
        }
    }
}
