<?php

namespace Signifyd\Connect\Model\ProcessCron;

use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Signifyd\Connect\Helper\ConfigHelper;
use Signifyd\Connect\Helper\OrderHelper;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\Api\Core\Client;
use Signifyd\Connect\Model\ResourceModel\Fulfillment as FulfillmentResourceModel;
use Magento\Sales\Model\OrderFactory;
use Signifyd\Connect\Model\ResourceModel\Order as SignifydOrderResourceModel;
use Signifyd\Connect\Model\Api\FulfillmentFactory;

class Fulfillment
{
    /**
     * @var ConfigHelper
     */
    protected $configHelper;

    /**
     * @var FulfillmentResourceModel
     */
    protected $fulfillmentResourceModel;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var OrderHelper
     */
    protected $orderHelper;

    /**
     * @var SignifydOrderResourceModel
     */
    protected $signifydOrderResourceModel;

    /**
     * @var OrderFactory
     */
    protected $orderFactory;

    /**
     * @var FulfillmentFactory
     */
    protected $fulfillmentFactory;

    /**
     * @var Client
     */
    protected $client;

    /**
     * @var JsonSerializer
     */
    protected $jsonSerializer;

    /**
     * Fulfillment constructor.
     * @param ConfigHelper $configHelper
     * @param FulfillmentResourceModel $fulfillmentResourceModel
     * @param Logger $logger
     * @param OrderHelper $orderHelper
     * @param SignifydOrderResourceModel $signifydOrderResourceModel
     * @param OrderFactory $orderFactory
     * @param FulfillmentFactory $fulfillmentFactory
     * @param Client $client
     * @param JsonSerializer $jsonSerializer
     */
    public function __construct(
        ConfigHelper $configHelper,
        FulfillmentResourceModel $fulfillmentResourceModel,
        Logger $logger,
        OrderHelper $orderHelper,
        SignifydOrderResourceModel $signifydOrderResourceModel,
        OrderFactory $orderFactory,
        FulfillmentFactory $fulfillmentFactory,
        Client $client,
        JsonSerializer $jsonSerializer
    ) {
        $this->configHelper = $configHelper;
        $this->fulfillmentResourceModel = $fulfillmentResourceModel;
        $this->logger = $logger;
        $this->orderHelper = $orderHelper;
        $this->signifydOrderResourceModel = $signifydOrderResourceModel;
        $this->orderFactory = $orderFactory;
        $this->fulfillmentFactory = $fulfillmentFactory;
        $this->client = $client;
        $this->jsonSerializer = $jsonSerializer;
    }

    /**
     * @param $fulfillments
     * @return void
     */
    public function __invoke($fulfillments)
    {
        foreach ($fulfillments as $fulfillment) {
            try {
                $orderId = $fulfillment->getOrderId();
                $order = $this->orderFactory->create();
                $this->signifydOrderResourceModel->load($order, $orderId, 'increment_id');
                $fulfillmentApi = $this->fulfillmentFactory->create();
                $fulfillmentData = $fulfillmentApi($fulfillment);

                $this->logger->info("Call addFulfillments with request: " .
                    $this->jsonSerializer->serialize($fulfillmentData), ['entity' => $order]);

                $fulfillmentBulkResponse = $this->client
                    ->getSignifydSaleApi($order)->addFulfillment($fulfillmentData);
                $fulfillmentOrderId = $fulfillmentBulkResponse->getOrderId();

                if (isset($fulfillmentOrderId) === false) {
                    $message = "CRON: Fullfilment failed to send";
                } else {
                    $this->logger->info("AddFulfillments response: " .
                        $this->jsonSerializer->serialize($fulfillmentBulkResponse), ['entity' => $order]);
                    $message = "CRON: Fullfilment sent";
                    $fulfillment->setMagentoStatus(\Signifyd\Connect\Model\Fulfillment::COMPLETED_STATUS);
                    $this->fulfillmentResourceModel->save($fulfillment);
                }

                $this->logger->debug($message, ['entity' => $order]);
                $this->orderHelper->addCommentToStatusHistory($order, $message);
            } catch (\Exception $e) {
                $this->logger->error(
                    "CRON: Failed to process fulfillment to order {$fulfillment->getOrderId()}: "
                    . $e->getMessage()
                );
            } catch (\Error $e) {
                $this->logger->error(
                    "CRON: Failed to process fulfillment to order {$fulfillment->getOrderId()}: "
                    . $e->getMessage()
                );
            }
        }
    }
}
