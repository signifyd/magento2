<?php

namespace Signifyd\Connect\Helper;

use Signifyd\Connect\Model\CasedataFactory;
use Signifyd\Connect\Model\Fulfillment;
use Signifyd\Connect\Model\FulfillmentFactory;
use Signifyd\Connect\Model\ResourceModel\Casedata as CasedataResourceModel;
use Signifyd\Connect\Model\ResourceModel\Fulfillment as FulfillmentResourceModel;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Core\SignifydModel;
use Magento\Framework\Serialize\SerializerInterface;
use Signifyd\Connect\Helper\OrderHelper;

class FulfillmentHelper
{
    /**
     * @var CasedataFactory
     */
    protected $casedataFactory;

    /**
     * @var FulfillmentFactory
     */
    protected $fulfillmentFactory;

    /**
     * @var CasedataResourceModel
     */
    protected $casedataResourceModel;

    /**
     * @var FulfillmentResourceModel
     */
    protected $fulfillmentResourceModel;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var ConfigHelper
     */
    protected $configHelper;

    /**
     * @var SerializerInterface
     */
    protected $serializer;

    /**
     * @var OrderHelper
     */
    protected $orderHelper;

    /**
     * FulfillmentHelper constructor.
     * @param CasedataFactory $casedataFactory
     * @param FulfillmentFactory $fulfillmentFactory
     * @param CasedataResourceModel $casedataResourceModel
     * @param FulfillmentResourceModel $fulfillmentResourceModel
     * @param Logger $logger
     * @param ConfigHelper $configHelper
     * @param SerializerInterface $serializer
     * @param OrderHelper $orderHelper
     */
    public function __construct(
        CasedataFactory $casedataFactory,
        FulfillmentFactory $fulfillmentFactory,
        CasedataResourceModel $casedataResourceModel,
        FulfillmentResourceModel $fulfillmentResourceModel,
        Logger $logger,
        ConfigHelper $configHelper,
        SerializerInterface $serializer,
        OrderHelper $orderHelper
    ) {
        $this->casedataFactory = $casedataFactory;
        $this->fulfillmentFactory = $fulfillmentFactory;

        $this->casedataResourceModel = $casedataResourceModel;
        $this->fulfillmentResourceModel = $fulfillmentResourceModel;

        $this->logger = $logger;
        $this->configHelper = $configHelper;
        $this->serializer = $serializer;
        $this->orderHelper = $orderHelper;
    }

    public function postFulfillmentToSignifyd(\Magento\Sales\Model\Order\Shipment $shipment)
    {
        if ($shipment->getId() <= 0) {
            $this->logger->info('Fulfillment will not proceed because shipment has no ID');
            return false;
        }

        $order = $shipment->getOrder();
        $orderIncrementId = $order->getIncrementId();

        $case = $this->casedataFactory->create();
        $this->casedataResourceModel->load($case, $orderIncrementId);

        $caseCode = $case instanceof \Signifyd\Connect\Model\Casedata ? $case->getCode() : null;

        if (empty($caseCode)) {
            $this->logger->info('Fulfillment will not proceed because no case has been found: ' . $orderIncrementId);
            return false;
        }

        try {
            $shipmentIncrementId = $shipment->getIncrementId();

            $this->logger->debug("Fulfillment for case order {$orderIncrementId}, shipment {$shipmentIncrementId}");

            $fulfillment = $this->getFulfillmentFromDatabase($shipmentIncrementId);

            if ($fulfillment->getId()) {
                $this->logger->debug("Fulfillment for shipment {$shipmentIncrementId} already sent");
                return false;
            } else {
                $fulfillmentData = $this->generateFulfillmentData($shipment);

                if ($fulfillmentData == false) {
                    $this->logger->debug("Fulfillment for shipment {$shipmentIncrementId} is not ready to be sent");
                    return false;
                }

                $fulfillment = $this->prepareFulfillmentToDatabase($fulfillmentData);
            }

            $id = $this->configHelper->getSignifydApi($order)->createFulfillment($orderIncrementId, $fulfillmentData);

            if ($id == false) {
                $message = "Signifyd: Fullfilment failed to send";
            } else {
                $message = "Signifyd: Fullfilment sent";

                $fulfillment->setMagentoStatus(Fulfillment::COMPLETED_STATUS);
                $this->fulfillmentResourceModel->save($fulfillment);
            }
        } catch (\Exception $e) {
            $this->logger->debug("Fulfillment error: {$e->getMessage()}");
            return false;
        }

        $this->logger->debug($message);
        $this->orderHelper->addCommentToStatusHistory($order, $message);

        return true;
    }

    /**
     * @param $shipmentIncrementId
     * @return \Signifyd\Connect\Model\Fulfillment
     */
    public function getFulfillmentFromDatabase($shipmentIncrementId)
    {
        $fulfillment = $this->fulfillmentFactory->create();
        $this->fulfillmentResourceModel->load($fulfillment, $shipmentIncrementId);
        return $fulfillment;
    }

    /**
     * @param \Signifyd\Models\Fulfillment $fulfillmentData
     * @return \Signifyd\Connect\Model\Fulfillment
     */
    public function prepareFulfillmentToDatabase(\Signifyd\Models\Fulfillment $fulfillmentData)
    {
        /** @var \Signifyd\Connect\Model\Fulfillment $fulfillment */
        $fulfillment = $this->fulfillmentFactory->create();
        $fulfillment->setData('id', $fulfillmentData->id);
        $fulfillment->setData('order_id', $fulfillmentData->orderId);
        $fulfillment->setData('created_at', $fulfillmentData->createdAt);
        $fulfillment->setData('delivery_email', $fulfillmentData->deliveryEmail);
        $fulfillment->setData('fulfillment_status', $fulfillmentData->fulfillmentStatus);
        $fulfillment->setData('tracking_numbers', $this->serialize($fulfillmentData->trackingNumbers));
        $fulfillment->setData('tracking_urls', $this->serialize($fulfillmentData->trackingUrls));
        $fulfillment->setData('products', $this->serialize($fulfillmentData->products));
        $fulfillment->setData('shipment_status', $fulfillmentData->shipmentStatus);
        $fulfillment->setData('delivery_address', $this->serialize($fulfillmentData->deliveryAddress));
        $fulfillment->setData('recipient_name', $fulfillmentData->recipientName);
        $fulfillment->setData('confirmation_name', $fulfillmentData->confirmationName);
        $fulfillment->setData('confirmation_phone', $fulfillmentData->confirmationPhone);
        $fulfillment->setData('shipping_carrier', $fulfillmentData->shippingCarrier);

        return $fulfillment;
    }

    /**
     * @param $data
     * @return bool|string
     */
    public function serialize($data)
    {
        try {
            return $this->serializer->serialize($data);
        } catch (\InvalidArgumentException $e) {
            return '';
        }
    }

    /**
     * @param \Magento\Sales\Model\Order\Shipment $shipment
     * @return bool|\Signifyd\Models\Fulfillment
     */
    public function generateFulfillmentData(\Magento\Sales\Model\Order\Shipment $shipment)
    {
        $trackingNumbers = $this->getTrackingNumbers($shipment);

        // At this moment fulfillment must be sent only if it has tracking numbers
        if (empty($trackingNumbers)) {
            return false;
        }

        /** @var \Signifyd\Models\Fulfillment $fulfillment */
        $fulfillment = SignifydModel::Make(\Signifyd\Models\Fulfillment::class);
        $fulfillment->id = $shipment->getIncrementId();
        $fulfillment->orderId = $shipment->getOrder()->getIncrementId();
        $fulfillment->createdAt = $this->getCreatedAt($shipment);
        $fulfillment->deliveryEmail = $this->getDeliveryEmail($shipment);
        $fulfillment->fulfillmentStatus = $this->getFulfillmentStatus($shipment);
        $fulfillment->trackingNumbers = $trackingNumbers;
        $fulfillment->trackingUrls = $this->getTrackingUrls($shipment);
        $fulfillment->products = $this->getProducts($shipment);
        $fulfillment->shipmentStatus = $this->getShipmentStatus($shipment);
        $fulfillment->deliveryAddress = $this->getDeliveryAddress($shipment);
        $fulfillment->recipientName = $shipment->getShippingAddress()->getName();
        $fulfillment->confirmationName = null;
        $fulfillment->confirmationPhone = null;
        $fulfillment->shippingCarrier = $shipment->getOrder()->getShippingMethod();

        return $fulfillment;
    }

    /**
     * @param \Magento\Sales\Model\Order\Shipment $shipment
     * @return array
     */
    public function getTrackingNumbers(\Magento\Sales\Model\Order\Shipment $shipment)
    {
        $trackingNumbers = [];

        $trackingCollection = $shipment->getTracksCollection();
        /**
         * Sometimes Magento loads tracking collection before the $shipment object gets loaded, leaving collection
         * without shipment filter. Forcing shipment filter to avoid issues.
         */
        $trackingCollection->setShipmentFilter($shipment->getId());

        /** @var \Magento\Sales\Model\Order\Shipment\Track $tracking */
        foreach ($trackingCollection->getItems() as $tracking) {
            $number = trim($tracking->getNumber());

            if (empty($number) === false && is_object($number) === false && is_array($number) === false &&
                in_array($number, $trackingNumbers) == false) {
                $trackingNumbers[] = $number;
            }
        }

        return $trackingNumbers;
    }

    /**
     * @param \Magento\Sales\Model\Order\Shipment $shipment
     * @return string
     */
    public function getCreatedAt(\Magento\Sales\Model\Order\Shipment $shipment)
    {
        $createdAt = $shipment->getCreatedAt();
        $createdAt = str_replace(' ', 'T', $createdAt) . 'Z';

        return $createdAt;
    }

    /**
     * @param \Magento\Sales\Model\Order\Shipment $shipment
     * @return string|null
     */
    public function getDeliveryEmail(\Magento\Sales\Model\Order\Shipment $shipment)
    {
        $isVirtual = true;

        /** @var Mage_Sales_Model_Order_Item $item */
        foreach ($shipment->getOrder()->getAllItems() as $item) {
            if ($item->getIsVirtual() == false) {
                $isVirtual = false;
                break;
            }
        }

        return $isVirtual ? $this->getDeliveryEmail($shipment) : null;
    }

    /**
     * Return fulfilment status. Valid status are: partial, complete, replacement
     *
     * @param \Magento\Sales\Model\Order\Shipment $shipment
     * @return string
     */
    public function getFulfillmentStatus(\Magento\Sales\Model\Order\Shipment $shipment)
    {
        $shipmentsCount = $shipment->getOrder()->getShipmentsCollection()->count();

        if ($shipment->getOrder()->canShip() == false) {
            return 'complete';
        } else {
            return 'partial';
        }
    }

    /**
     * Magento default tracking URLs are not accessible if you're not logged in
     *
     * @param \Magento\Sales\Model\Order\Shipment $shipment
     * @return array
     */
    public function getTrackingUrls(\Magento\Sales\Model\Order\Shipment $shipment)
    {
        return [];
    }

    /**
     * @param \Magento\Sales\Model\Order\Shipment $shipment
     * @return array
     */
    public function getProducts(\Magento\Sales\Model\Order\Shipment $shipment)
    {
        $products = [];

        /** @var \Magento\Sales\Model\Order\Shipment\Item $item */
        foreach ($shipment->getAllItems() as $item) {
            /**
             * About fields itemCategory and itemSubCategory, Chris Morris has explained on MAG-286
             *
             * This is meant to determine which products that were in the create case are associated to the fulfillment.
             * Since we don’t pass itemSubCategory or itemCategory in the create case we should keep these empty.
             */

            /** @var \Signifyd\Models\Product $product */
            $product = SignifydModel::Make(\Signifyd\Models\Product::class);
            $product->itemId = $item->getSku();
            $product->itemName = $item->getName();
            $product->itemIsDigital = (bool) $item->getOrderItem()->getIsVirtual();
            $product->itemCategory = null;
            $product->itemSubCategory = null;
            $product->itemUrl = $this->getItemUrl($item);
            $product->itemImage = $this->getItemImage($item);
            $product->itemQuantity = floatval($item->getQty());
            $product->itemPrice = floatval($item->getPrice());
            $product->itemWeight = floatval($item->getWeight());

            $products[] = $product;
        }

        return $products;
    }

    /**
     * @param \Magento\Sales\Model\Order\Shipment\Item $item
     * @return string
     */
    public function getItemUrl(\Magento\Sales\Model\Order\Shipment\Item $item)
    {
        return $item->getOrderItem()->getProduct()->getUrlInStore();
    }

    /**
     * @param \Magento\Sales\Model\Order\Shipment\Item $item
     * @return null|string
     */
    public function getItemImage(\Magento\Sales\Model\Order\Shipment\Item $item)
    {
        /** @var \Magento\Catalog\Model\Product $product */
        $product = $item->getOrderItem()->getProduct();

        try {
            $imageUrl = $product->getImage();
            $imageUrl = $product->getMediaConfig()->getMediaUrl($imageUrl);
        } catch (\Exception $e) {
            $imageUrl = null;
        }

        return $imageUrl;
    }

    /**
     * Magento do not track shipment stauts
     *
     * Rewrite this method if you have and want to send these informations to Signifyd
     *
     * @param \Magento\Sales\Model\Order\Shipment $shipment
     * @return null
     */
    public function getShipmentStatus(\Magento\Sales\Model\Order\Shipment $shipment)
    {
        $validShipmentStatus = [
            'in transit',
            'out for delivery',
            'waiting for pickup',
            'failed attempt',
            'delivered',
            'exception'
        ];

        return null;
    }

    /**
     * @param \Magento\Sales\Model\Order\Shipment $shipment
     * @return \Signifyd\Models\Address
     */
    public function getDeliveryAddress(\Magento\Sales\Model\Order\Shipment $shipment)
    {
        /** @var \Signifyd\Models\Address $deliveryAddress */
        $deliveryAddress = SignifydModel::Make(\Signifyd\Models\Address::class);
        $deliveryAddress->streetAddress = $this->getStreetAddress($shipment);
        $deliveryAddress->unit = null;
        $deliveryAddress->city = $shipment->getShippingAddress()->getCity();
        $deliveryAddress->provinceCode = $shipment->getShippingAddress()->getRegionCode();
        $deliveryAddress->postalCode = $shipment->getShippingAddress()->getPostcode();
        $deliveryAddress->countryCode = $shipment->getShippingAddress()->getCountry();

        return $deliveryAddress;
    }

    /**
     * @param \Magento\Sales\Model\Order\Shipment $shipment
     * @return string
     */
    public function getStreetAddress(\Magento\Sales\Model\Order\Shipment $shipment)
    {
        $street = $shipment->getShippingAddress()->getStreet();
        return implode(', ', $street);
    }
}
