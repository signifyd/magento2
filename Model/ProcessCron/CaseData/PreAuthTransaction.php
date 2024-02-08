<?php

namespace Signifyd\Connect\Model\ProcessCron\CaseData;

use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\ResourceModel\Order as OrderResourceModel;
use Magento\Store\Model\StoreManagerInterface;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\ResourceModel\Order as SignifydOrderResourceModel;
use Signifyd\Connect\Model\Api\CaseData\PreAuth\ProcessTransactionFactory;

class PreAuthTransaction
{
    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var OrderResourceModel
     */
    protected $orderResourceModel;

    /**
     * @var OrderFactory
     */
    protected $orderFactory;

    /**
     * @var SignifydOrderResourceModel
     */
    protected $signifydOrderResourceModel;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManagerInterface;

    /**
     * @var ProcessTransactionFactory
     */
    protected $processTransactionFactory;

    /**
     * PreAuthTransaction constructor.
     * @param Logger $logger
     * @param OrderResourceModel $orderResourceModel
     * @param OrderFactory $orderFactory
     * @param SignifydOrderResourceModel $signifydOrderResourceModel
     * @param StoreManagerInterface $storeManagerInterface
     * @param ProcessTransactionFactory $processTransactionFactory
     */
    public function __construct(
        Logger $logger,
        OrderResourceModel $orderResourceModel,
        OrderFactory $orderFactory,
        SignifydOrderResourceModel $signifydOrderResourceModel,
        StoreManagerInterface $storeManagerInterface,
        ProcessTransactionFactory $processTransactionFactory
    ) {
        $this->signifydOrderResourceModel = $signifydOrderResourceModel;
        $this->storeManagerInterface = $storeManagerInterface;
        $this->orderFactory = $orderFactory;
        $this->logger = $logger;
        $this->orderResourceModel = $orderResourceModel;
        $this->processTransactionFactory = $processTransactionFactory;
    }

    /**
     * @param array $preAuthCases
     * @return void
     */
    public function __invoke(array $preAuthCases)
    {
        $previousStore = $this->storeManagerInterface->getStore()->getId();

        /** @var \Signifyd\Connect\Model\Casedata $case */
        foreach ($preAuthCases as $case) {
            try {
                $order = $this->orderFactory->create();
                $this->signifydOrderResourceModel->load($order, $case->getData('order_id'));
                $this->storeManagerInterface->setCurrentStore($order->getStore()->getStoreId());

                if ($order->hasInvoices() === false) {
                    $this->logger->debug(
                        "CRON: there is no invoice created" .
                        ", transaction will not be processed for case no: {$case['order_increment']}",
                        ['entity' => $case]
                    );
                    continue;
                }

                $this->logger->debug(
                    "CRON: preparing to process transaction for case no: {$case['order_increment']}",
                    ['entity' => $case]
                );

                $processTransaction = $this->processTransactionFactory->create();
                $processTransaction($order);
            } catch (\Exception $e) {
                $this->logger->error(
                    "CRON: Failed to process transaction for case {$case->getId()}: "
                    . $e->getMessage()
                );
            } catch (\Error $e) {
                $this->logger->error(
                    "CRON: Failed to process transaction for case {$case->getId()}: "
                    . $e->getMessage()
                );
            }
        }

        $this->storeManagerInterface->setCurrentStore($previousStore);
    }
}
