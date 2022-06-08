<?php

namespace Signifyd\Connect\Helper;

use Signifyd\Connect\Helper\PurchaseHelper;
use Signifyd\Connect\Model\CasedataFactory;
use Signifyd\Connect\Model\Fulfillment;
use Signifyd\Connect\Model\FulfillmentFactory;
use Signifyd\Connect\Model\ResourceModel\Casedata as CasedataResourceModel;
use Signifyd\Connect\Model\ResourceModel\Fulfillment as FulfillmentResourceModel;
use Signifyd\Connect\Logger\Logger;
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
     * @var PurchaseHelper
     */
    protected $purchaseHelper;

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
     * @param PurchaseHelper $purchaseHelper
     */
    public function __construct(
        CasedataFactory $casedataFactory,
        FulfillmentFactory $fulfillmentFactory,
        CasedataResourceModel $casedataResourceModel,
        FulfillmentResourceModel $fulfillmentResourceModel,
        Logger $logger,
        ConfigHelper $configHelper,
        SerializerInterface $serializer,
        OrderHelper $orderHelper,
        PurchaseHelper $purchaseHelper
    ) {
        $this->casedataFactory = $casedataFactory;
        $this->fulfillmentFactory = $fulfillmentFactory;

        $this->casedataResourceModel = $casedataResourceModel;
        $this->fulfillmentResourceModel = $fulfillmentResourceModel;

        $this->logger = $logger;
        $this->configHelper = $configHelper;
        $this->serializer = $serializer;
        $this->orderHelper = $orderHelper;
        $this->purchaseHelper = $purchaseHelper;
    }

    public function postFulfillmentToSignifyd(\Magento\Sales\Model\Order\Shipment $shipment)
    {
        if ($shipment->getId() <= 0) {
            $this->logger->info('Fulfillment will not proceed because shipment has no ID');
            return false;
        }

        $order = $shipment->getOrder();
        $orderIncrementId = $order->getIncrementId();
        $orderId = $order->getId();

        $case = $this->casedataFactory->create();
        $this->casedataResourceModel->load($case, $orderId, 'order_id');

        $caseCode = $case instanceof \Signifyd\Connect\Model\Casedata ? $case->getCode() : null;

        if (empty($caseCode)) {
            $this->logger->info(
                "Fulfillment will not proceed because no case has been found: {$orderIncrementId} ({$orderId})"
            );
            return false;
        }

        try {
            $shipmentIncrementId = $shipment->getIncrementId();

            $this->logger->debug(
                "Fulfillment for case order  {$orderIncrementId} ({$orderId}), shipment {$shipmentIncrementId}"
            );

            $fulfillment = $this->getFulfillmentFromDatabase($shipmentIncrementId);

            if ($fulfillment->getId()) {
                $this->logger->debug("Fulfillment for shipment {$shipmentIncrementId} already sent");
                return false;
            }

            $fulfillmentData = $this->generateFulfillmentData($shipment);

            if ($fulfillmentData == false) {
                $this->logger->debug("Fulfillment for shipment {$shipmentIncrementId} is not ready to be sent");
                return false;
            }

            $fulfillment = $this->prepareFulfillmentToDatabase($fulfillmentData);
            $this->fulfillmentResourceModel->save($fulfillment);
        } catch (\Exception $e) {
            $this->logger->debug("Fulfillment error: {$e->getMessage()}");
            return false;
        }

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
     * @param array $fulfillmentData
     * @return \Signifyd\Connect\Model\Fulfillment
     */
    public function prepareFulfillmentToDatabase(array $fulfillmentData)
    {
        /** @var \Signifyd\Connect\Model\Fulfillment $fulfillment */
        $fulfillment = $this->fulfillmentFactory->create();
        $fulfillment->setData('id', $fulfillmentData['shipmentId']);
        $fulfillment->setData('order_id', $fulfillmentData['orderId']);
        $fulfillment->setData('shipped_at', $fulfillmentData['shippedAt']);
        $fulfillment->setData('fulfillment_status', $fulfillmentData['fulfillmentStatus']);
        $fulfillment->setData('products', $this->serialize($fulfillmentData['products']));
        $fulfillment->setData('shipment_status', $fulfillmentData['shipmentStatus']);
        $fulfillment->setData('tracking_urls', $this->serialize($fulfillmentData['trackingUrls']));
        $fulfillment->setData('tracking_numbers', $this->serialize($fulfillmentData['trackingNumbers']));
        $fulfillment->setData('destination', $this->serialize($fulfillmentData['destination']));
        $fulfillment->setData('origin', $this->serialize($fulfillmentData['origin']));
        $fulfillment->setData('carrier', $fulfillmentData['carrier']);

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
     * @return bool|array
     */
    public function generateFulfillmentData(\Magento\Sales\Model\Order\Shipment $shipment)
    {
        $trackingNumbers = $this->getTrackingNumbers($shipment);

        // At this moment fulfillment must be sent only if it has tracking numbers
        if (empty($trackingNumbers)) {
            return false;
        }

        $fulfillment = [];
        $fulfillment['orderId'] = $shipment->getOrder()->getIncrementId();
        $fulfillment['fulfillmentStatus'] = $this->getFulfillmentStatus($shipment);
        $fulfillment['shipmentId'] = $shipment->getIncrementId();
        $fulfillment['shippedAt'] = $this->getCreatedAt($shipment);
        $fulfillment['products'] = $this->getProducts($shipment);
        $fulfillment['shipmentStatus'] = $this->getShipmentStatus($shipment);
        $fulfillment['trackingUrls'] = $this->getTrackingUrls($shipment);
        $fulfillment['trackingNumbers'] = $trackingNumbers;
        $fulfillment['destination'] = $this->makeDestination($shipment);
        $fulfillment['origin'] = $this->purchaseHelper->makeOrigin($shipment->getOrder()->getStoreId());
        $fulfillment['carrier'] = $this->purchaseHelper
            ->makeShipper($shipment->getOrder()->getShippingMethod());

        return $fulfillment;
    }

    public function makeDestination(\Magento\Sales\Model\Order\Shipment $shipment)
    {
        $destination = [];
        $firstname = $shipment->getOrder()->getBillingAddress()->getFirstname();
        $lastname = $shipment->getOrder()->getBillingAddress()->getLastname();
        $destination['fullName'] = $firstname . ' ' . $lastname;
        $destination['address'] = $this->getDeliveryAddress($shipment);

        return $destination;
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
            return 'COMPLETE';
        } else {
            return 'PARTIAL';
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

            $product = [];
            $product['itemName'] = $item->getName();
            $product['itemQuantity'] = floatval($item->getQty());

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
        $product = $item->getOrderItem()->getProduct();

        if (isset($product)) {
            return $product->getUrlInStore();
        }

        return null;
    }

    /**
     * @param \Magento\Sales\Model\Order\Shipment\Item $item
     * @return null|string
     */
    public function getItemImage(\Magento\Sales\Model\Order\Shipment\Item $item)
    {
        /** @var \Magento\Catalog\Model\Product $product */
        $product = $item->getOrderItem()->getProduct();

        if (isset($product) === false) {
            return null;
        }

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
     * Rewrite/plugin this method if you have and want to send these informations to Signifyd
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
     * @return array
     */
    public function getDeliveryAddress(\Magento\Sales\Model\Order\Shipment $shipment)
    {
        $deliveryAddress = [];
        $deliveryAddress['streetAddress'] = $this->getStreetAddress($shipment);
        $deliveryAddress['unit'] = null;
        $deliveryAddress['city'] = $shipment->getShippingAddress()->getCity();
        $deliveryAddress['provinceCode'] = $shipment->getShippingAddress()->getRegionCode();
        $deliveryAddress['postalCode'] = $shipment->getShippingAddress()->getPostcode();
        $deliveryAddress['countryCode'] = $shipment->getShippingAddress()->getCountry();

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
