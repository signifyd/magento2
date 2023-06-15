<?php
/**
 * Copyright 2015 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */
namespace Signifyd\Connect\Model\UpdateOrder;

use Magento\Sales\Model\Order\CreditmemoFactory;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\ResourceModel\Order as OrderResourceModel;
use Magento\Sales\Model\Service\CreditmemoService;
use Signifyd\Connect\Helper\ConfigHelper;
use Signifyd\Connect\Helper\OrderHelper;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\ResourceModel\Order as SignifydOrderResourceModel;

/**
 * Defines link data for the comment field in the config page
 */
class Refund
{
    /**
     * @var ConfigHelper
     */
    protected $configHelper;

    /**
     * @var OrderHelper
     */
    protected $orderHelper;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var OrderResourceModel
     */
    protected $orderResourceModel;

    /**
     * @var CreditmemoFactory
     */
    protected $creditmemoFactory;

    /**
     * @var CreditmemoService
     */
    protected $creditmemoService;

    /**
     * @var OrderFactory
     */
    protected $orderFactory;

    /**
     * @var SignifydOrderResourceModel
     */
    protected $signifydOrderResourceModel;

    /**
     * @param ConfigHelper $configHelper
     * @param OrderHelper $orderHelper
     * @param Logger $logger
     * @param OrderResourceModel $orderResourceModel
     * @param CreditmemoFactory $creditmemoFactory
     * @param CreditmemoService $creditmemoService
     * @param OrderFactory $orderFactory
     * @param SignifydOrderResourceModel $signifydOrderResourceModel
     */
    public function __construct(
        ConfigHelper $configHelper,
        OrderHelper $orderHelper,
        Logger $logger,
        OrderResourceModel $orderResourceModel,
        CreditmemoFactory $creditmemoFactory,
        CreditmemoService $creditmemoService,
        OrderFactory $orderFactory,
        SignifydOrderResourceModel $signifydOrderResourceModel
    ) {
        $this->configHelper = $configHelper;
        $this->orderHelper = $orderHelper;
        $this->logger = $logger;
        $this->orderResourceModel = $orderResourceModel;
        $this->creditmemoFactory = $creditmemoFactory;
        $this->creditmemoService = $creditmemoService;
        $this->orderFactory = $orderFactory;
        $this->signifydOrderResourceModel = $signifydOrderResourceModel;
    }

    public function __invoke($order, $case, $completeCase)
    {
        try {
            if ($order->canUnhold()) {
                $order->unhold();
                $this->orderResourceModel->save($order);
            }

            $order = $this->orderFactory->create();
            $this->signifydOrderResourceModel->load($order, $case->getData('order_id'));

            $invoices = $order->getInvoiceCollection();

            if ($invoices->getTotalCount() > 0) {
                $this->createInvoicesCreditMemo($invoices, $order);
            } else {
                if ($order->canHold()) {
                    $order->hold();
                    $this->signifydOrderResourceModel->save($order);
                }

                $message = "Signifyd: tried to refund, but there is no invoice to add credit memo";
                $this->orderHelper->addCommentToStatusHistory($order, $message);
                $this->logger->debug(
                    "tried to refund, but there is no invoice to add credit memo",
                    ['entity' => $order]
                );
            }

            $completeCase = true;
        } catch (\Exception $e) {
            $order = $this->orderFactory->create();
            $this->signifydOrderResourceModel->load($order, $case->getData('order_id'));
            $case->setEntries('fail', 1);

            if ($order->canHold()) {
                $order->hold();
                $this->signifydOrderResourceModel->save($order);
            }

            $this->logger->debug(
                'Exception creating creditmemo: ' . $e->__toString(),
                ['entity' => $order]
            );

            $this->orderHelper->addCommentToStatusHistory(
                $order,
                "Signifyd: unable to create creditmemo: {$e->getMessage()}"
            );
        }

        return $completeCase;
    }

    /**
     * @param $invoices
     * @param $order
     * @return void
     */
    protected function createInvoicesCreditMemo($invoices, $order)
    {
        foreach ($invoices as $invoice) {
            $creditmemo = $this->creditmemoFactory->createByOrder($order);
            $creditmemo->setInvoice($invoice);
            $this->creditmemoService->refund($creditmemo);
            $this->logger->debug(
                'Credit memo was created for order: ' . $order->getIncrementId(),
                ['entity' => $order]
            );
        }
    }
}
