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
    )
    {
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
            $this->orderResourceModel->load($order, $orderId , 'increment_id');
            $fulfillmentData = $this->generateFulfillmentData($fulfillment);
            $fulfillmentBulkResponse = $this->configHelper->getSignifydCaseApi($order)->addFulfillment($fulfillmentData);

            if ($fulfillmentBulkResponse->isError()) {
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
        $fulfillmentData['id'] = $fulfillment->getData('id');
        $fulfillmentData['orderId'] = $fulfillment->getData('order_id');
        $fulfillmentData['createdAt'] = $fulfillment->getData('created_at');
        $fulfillmentData['deliveryEmail'] = $fulfillment->getData('delivery_email');
        $fulfillmentData['fulfillmentStatus'] = $fulfillment->getData('fulfillment_status');
        $fulfillmentData['trackingNumbers'] = $fulfillment->getData('tracking_numbers');
        $fulfillmentData['trackingUrls'] = $fulfillment->getData('tracking_urls');
        $fulfillmentData['products'] = $this->getFulfillmentsProducts($fulfillment);
        $fulfillmentData['shipmentStatus'] = $fulfillment->getData('shipment_status');
        $fulfillmentData['deliveryAddress'] = $fulfillment->getData('delivery_address');
        $fulfillmentData['recipientName'] = $fulfillment->getData('recipient_name');
        $fulfillmentData['confirmationName'] = null;
        $fulfillmentData['confirmationPhone'] = null;
        $fulfillmentData['shippingCarrier'] = $fulfillment->getData('shipping_carrier');

        return $fulfillmentData;
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
