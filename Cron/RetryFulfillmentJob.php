<?php

namespace Signifyd\Connect\Cron;

use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Signifyd\Connect\Helper\ConfigHelper;
use Signifyd\Connect\Helper\OrderHelper;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\Fulfillment;
use Signifyd\Connect\Model\ResourceModel\Fulfillment as FulfillmentResourceModel;
use Signifyd\Connect\Model\ResourceModel\Fulfillment\CollectionFactory as FulfillmentCollectionFactory;
use Magento\Sales\Model\ResourceModel\Order as OrderResourceModel;
use Magento\Sales\Model\OrderFactory;
use Signifyd\Connect\Helper\RetryFulfillment;

class RetryFulfillmentJob
{
    /**
     * @var FulfillmentCollectionFactory
     */
    protected $fulfillmentCollectionFactory;

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
     * @var RetryFulfillment
     */
    protected $fulfillmentRetryObj;

    /**
     * @var OrderResourceModel
     */
    protected $orderResourceModel;

    /**
     * @var OrderFactory
     */
    protected $orderFactory;

    /**
     * @var JsonSerializer
     */
    protected $jsonSerializer;

    /**
     * RetryFulfillmentJob constructor.
     * @param FulfillmentCollectionFactory $fulfillmentCollectionFactory
     * @param ConfigHelper $configHelper
     * @param FulfillmentResourceModel $fulfillmentResourceModel
     * @param Logger $logger
     * @param OrderHelper $orderHelper
     * @param RetryFulfillment $fulfillmentRetryObj
     * @param OrderResourceModel $orderResourceModel
     * @param OrderFactory $orderFactory
     * @param JsonSerializer $jsonSerializer
     */
    public function __construct(
        FulfillmentCollectionFactory $fulfillmentCollectionFactory,
        ConfigHelper $configHelper,
        FulfillmentResourceModel $fulfillmentResourceModel,
        Logger $logger,
        OrderHelper $orderHelper,
        RetryFulfillment $fulfillmentRetryObj,
        OrderResourceModel $orderResourceModel,
        OrderFactory $orderFactory,
        JsonSerializer $jsonSerializer
    ) {
        $this->fulfillmentCollectionFactory = $fulfillmentCollectionFactory;
        $this->configHelper = $configHelper;
        $this->fulfillmentResourceModel = $fulfillmentResourceModel;
        $this->logger = $logger;
        $this->orderHelper = $orderHelper;
        $this->fulfillmentRetryObj = $fulfillmentRetryObj;
        $this->orderResourceModel = $orderResourceModel;
        $this->orderFactory = $orderFactory;
        $this->jsonSerializer = $jsonSerializer;
    }

    /**
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     * @throws \Signifyd\Core\Exceptions\FulfillmentException
     * @throws \Signifyd\Core\Exceptions\InvalidClassException
     */
    public function execute()
    {
        $this->logger->debug("CRON: Retry fulfillment method called");

        $fulfillments = $this->fulfillmentRetryObj->getRetryFulfillment();

        foreach ($fulfillments as $fulfillment) {
            $orderId = $fulfillment->getOrderId();
            $order = $this->orderFactory->create();
            $this->orderResourceModel->load($order, $orderId, 'increment_id');
            $fulfillmentData = $this->generateFulfillmentData($fulfillment);

            $fulfillmentBulkResponse = $this->configHelper
                ->getSignifydSaleApi($order)->addFulfillment($fulfillmentData);
            $fulfillmentOrderId = $fulfillmentBulkResponse->getOrderId();

            if (isset($fulfillmentOrderId) === false) {
                $message = "CRON: Fullfilment failed to send";
            } else {
                $message = "CRON: Fullfilment sent";
                $fulfillment->setMagentoStatus(Fulfillment::COMPLETED_STATUS);
                $this->fulfillmentResourceModel->save($fulfillment);
            }

            $this->logger->debug($message);
            $this->orderHelper->addCommentToStatusHistory($order, $message);
        }

        $this->logger->debug("CRON: Retry fulfillment method ended");
    }

    /**
     * @param $fulfillment
     * @return array
     */
    public function generateFulfillmentData($fulfillment)
    {
        $fulfillmentData = [];
        $fulfillmentData['orderId'] = $fulfillment->getData('order_id');
        $fulfillmentData['fulfillmentStatus'] = $fulfillment->getData('fulfillment_status');
        $fulfillmentData['fulfillments'] = $this->makeFulfillments($fulfillment);

        return $fulfillmentData;
    }

    public function makeFulfillments($fulfillmentData)
    {
        $fulfillments = [];
        $fulfillment = [];
        $fulfillment['shipmentId'] = $fulfillmentData->getData('id');
        $fulfillment['shippedAt'] = $fulfillmentData->getData('shipped_at');
        $fulfillment['products'] = $this->getFulfillmentsProducts($fulfillmentData);
        $fulfillment['shipmentStatus'] = $fulfillmentData->getData('shipment_status');
        $fulfillment['trackingUrls'] = $fulfillmentData->getData('tracking_urls');
        $fulfillment['trackingNumbers'] = $fulfillmentData->getData('tracking_numbers');
        $fulfillment['destination'] = $this->jsonSerializer->unserialize($fulfillmentData->getData('destination'));
        $fulfillment['origin'] = $this->jsonSerializer->unserialize($fulfillmentData->getData('origin'));
        $fulfillment['carrier'] = $fulfillmentData->getData('carrier');

        $fulfillments[] = $fulfillment;
        return $fulfillments;
    }

    /**
     * @param $fulfillment
     * @return array|bool|float|int|mixed|string|null
     */
    public function getFulfillmentsProducts($fulfillment)
    {
        $productsArray = $this->jsonSerializer->unserialize($fulfillment->getProducts());
        return $productsArray;
    }
}
