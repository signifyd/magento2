<?php

namespace Signifyd\Connect\Helper;

use Signifyd\Connect\Model\ResourceModel\Casedata as CasedataResourceModel;
use Signifyd\Connect\Model\CasedataFactory;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Core\SignifydModel;
use Magento\Sales\Model\ResourceModel\Order as OrderResourceModel;

class FulfillmentHelper
{
    /**
     * @var CasedataFactory
     */
    protected $casedataFactory;

    /**
     * @var CasedataResourceModel
     */
    protected $casedataResourceModel;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var ConfigHelper
     */
    protected $configHelper;

    /**
     * @var OrderResourceModel
     */
    protected $orderResourceModel;

    public function __construct(
        CasedataFactory $casedataFactory,
        CasedataResourceModel $casedataResourceModel,
        Logger $logger,
        ConfigHelper $configHelper,
        OrderResourceModel $orderResourceModel
    ) {
        $this->casedataFactory = $casedataFactory;
        $this->casedataResourceModel = $casedataResourceModel;
        $this->logger = $logger;
        $this->configHelper = $configHelper;
        $this->orderResourceModel = $orderResourceModel;
    }

    public function postFulfillmentToSignifyd(\Magento\Sales\Model\Order\Shipment $shipment)
    {
        if ($shipment->getId() <= 0) {
            return false;
        }

        $order = $shipment->getOrder();
        $orderIncrementId = $order->getIncrementId();

        $case = $this->casedataFactory->create();
        $this->casedataResourceModel->load($case, $orderIncrementId);

        $caseCode = $case instanceof \Signifyd\Connect\Model\Casedata ? $case->getCode() : null;

        if (empty($caseCode)) {
            return false;
        }

        if ($case->getEntries('fulfilled') == 1) {
            return false;
        }

        try {
            $this->logger->debug("Fulfillment for case order {$orderIncrementId}");

            $fulfillment = $this->generateFulfillment($shipment);

            if ($fulfillment == false) {
                return false;
            }

            $id = $this->configHelper->getSignifydApi($order)->createFulfillment($orderIncrementId, $fulfillment);

            if ($id == false) {
                $message = "Signifyd: Fullfilment failed to send";
            } else {
                $message = "Signifyd: Fullfilment sent";

                $case->setEntries('fulfilled', 1);
                $this->casedataResourceModel->save($case);
            }
        } catch (Exception $e) {
            $this->logger->debug("Fulfillment error: {$e->getMessage()}");
            return false;
        }

        $this->logger->debug($message);

        $order->addStatusHistoryComment($message);
        $this->orderResourceModel->save($order);

        return true;
    }

    /**
     * @param \Magento\Sales\Model\Order\Shipment $shipment
     * @return bool|\Signifyd\Models\Fulfillment
     */
    public function generateFulfillment(\Magento\Sales\Model\Order\Shipment $shipment)
    {
        $trackingNumbers = $this->getTrackingNumbers($shipment);

        // At this moment fulfillment must be sent only if it has tracking numbers
        if (empty($trackingNumbers)) {
            return false;
        }

        /** @var \Signifyd\Models\Fulfillment $fulfillment */
        $fulfillment = SignifydModel::Make("\\Signifyd\\Models\\Fulfillment");
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
        $trackingNumbers = array();

        $trackingCollection = $shipment->getTracksCollection();

        /** @var \Magento\Sales\Model\Order\Shipment\Track $tracking */
        foreach ($trackingCollection->getItems() as $tracking) {
            $number = trim($tracking->getNumber());

            if (empty($number) == false) {
                $trackingNumbers[] = $tracking->getNumber();
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

        if ($shipmentsCount == 1 && $shipment->getOrder()->canShip() == false) {
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
        return array();
    }

    /**
     * @param \Magento\Sales\Model\Order\Shipment $shipment
     * @return array
     */
    public function getProducts(\Magento\Sales\Model\Order\Shipment $shipment)
    {
        $products = array();

        /** @var \Magento\Sales\Model\Order\Shipment\Item $item */
        foreach ($shipment->getAllItems() as $item) {
            /**
             * About fields itemCategory and itemSubCategory, Chris Morris has explained on MAG-286
             *
             * This is meant to determine which products that were in the create case are associated to the fulfillment.
             * Since we don’t pass itemSubCategory or itemCategory in the create case we should keep these empty.
             */

            /** @var \Signifyd\Models\Product $product */
            $product = SignifydModel::Make("\\Signifyd\\Models\\Product");
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
        } catch (Exception $e) {
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
        $validShipmentStatus = array(
            'in transit',
            'out for delivery',
            'waiting for pickup',
            'failed attempt',
            'delivered',
            'exception'
        );

        return null;
    }

    /**
     * @param \Magento\Sales\Model\Order\Shipment $shipment
     * @return \Signifyd\Models\Address
     */
    public function getDeliveryAddress(\Magento\Sales\Model\Order\Shipment $shipment)
    {
        /** @var \Signifyd\Models\Address $deliveryAddress */
        $deliveryAddress = SignifydModel::Make("\\Signifyd\\Models\\Address");
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