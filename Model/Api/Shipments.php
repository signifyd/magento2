<?php

namespace Signifyd\Connect\Model\Api;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use Magento\Store\Model\ScopeInterface;

class Shipments
{
    /**
     * @var ScopeConfigInterface
     */
    public $scopeConfigInterface;

    /**
     * @var JsonSerializer
     */
    public $jsonSerializer;

    /**
     * @var CarrierFactory
     */
    public $carrierFactory;

    /**
     * @var RecipientFactory
     */
    public $recipientFactory;

    /**
     * @var OriginFactory
     */
    public $originFactory;

    /**
     * @var MinDeliveryDateFactory
     */
    public $minDeliveryDateFactory;

    /**
     * Shipments construct.
     *
     * @param ScopeConfigInterface $scopeConfigInterface
     * @param JsonSerializer $jsonSerializer
     * @param CarrierFactory $carrierFactory
     * @param RecipientFactory $recipientFactory
     * @param OriginFactory $originFactory
     * @param MinDeliveryDateFactory $minDeliveryDateFactory
     */
    public function __construct(
        ScopeConfigInterface $scopeConfigInterface,
        JsonSerializer $jsonSerializer,
        CarrierFactory $carrierFactory,
        RecipientFactory $recipientFactory,
        OriginFactory $originFactory,
        MinDeliveryDateFactory $minDeliveryDateFactory
    ) {
        $this->scopeConfigInterface = $scopeConfigInterface;
        $this->jsonSerializer = $jsonSerializer;
        $this->carrierFactory = $carrierFactory;
        $this->recipientFactory = $recipientFactory;
        $this->originFactory = $originFactory;
        $this->minDeliveryDateFactory = $minDeliveryDateFactory;
    }

    /**
     * Construct a new Shipment object
     *
     * @param Order|Quote $entity
     * @return array
     */
    public function __invoke($entity)
    {
        if ($entity instanceof Order) {
            $shipments = $this->makeShipments($entity);
        } elseif ($entity instanceof Quote) {
            $shipments = $this->makeShipmentsFromQuote($entity);
        } else {
            $shipments = [];
        }

        return $shipments;
    }

    /**
     * Make shipments method.
     *
     * @param Order $order
     * @return array
     */
    protected function makeShipments(Order $order)
    {
        $shipments = [];
        $shippingMethod = $order->getShippingMethod(true);

        $shipment = [];
        $shipment['destination'] = ($this->recipientFactory->create())($order);
        $shipment['origin'] = ($this->originFactory->create())($order->getStoreId());
        $shipment['carrier'] = ($this->carrierFactory->create())($shippingMethod);
        $shipment['minDeliveryDate'] = ($this->minDeliveryDateFactory->create())();
        $shipment['maxDeliveryDate'] = null;
        $shipment['shipmentId'] = null;
        $shipment['fulfillmentMethod'] = $this->getFulfillmentMethodMapping(
            $order->getShippingMethod(),
            ScopeInterface::SCOPE_STORES,
            $order->getStoreId()
        );

        $shipments[] = $shipment;

        foreach ($order->getItems() as $item) {
            if ($item->getProductType() == 'giftcard') {
                $shipmentGc = [];
                $shipmentGc['destination'] = [
                    'email' => $item->getProductOptions()['giftcard_recipient_email'],
                    'fullName' => $item->getProductOptions()['giftcard_recipient_name']
                ];

                $shipments[] = $shipmentGc;
            }
        }

        return $shipments;
    }

    /**
     * Make shipments from quote method.
     *
     * @param Quote $quote
     * @return array
     */
    protected function makeShipmentsFromQuote(Quote $quote)
    {
        $shipments = [];
        $shippingMethod = $quote->getShippingAddress()->getShippingMethod();

        $shipment = [];
        $shipment['destination'] = ($this->recipientFactory->create())($quote);
        $shipment['origin'] = ($this->originFactory->create())($quote->getStoreId());
        $shipment['carrier'] = ($this->carrierFactory->create())($shippingMethod);
        $shipment['minDeliveryDate'] = ($this->minDeliveryDateFactory->create())();
        $shipment['maxDeliveryDate'] = null;
        $shipment['shipmentId'] = null;
        $shipment['fulfillmentMethod'] = $this->getFulfillmentMethodMapping(
            $quote->getShippingAddress()->getShippingMethod(),
            ScopeInterface::SCOPE_STORES,
            $quote->getStoreId()
        );

        $shipments[] = $shipment;

        foreach ($quote->getAllItems() as $item) {
            if ($item->getProductType() == 'giftcard') {
                $shipmentGc = [];
                $shipmentGc['destination'] = [
                    'email' => $item->getProductOptions()['giftcard_recipient_email'],
                    'fullName' => $item->getProductOptions()['giftcard_recipient_name']
                ];

                $shipments[] = $shipmentGc;
            }
        }

        return $shipments;
    }

    /**
     * Get fulfillment method mapping method.
     *
     * @param mixed $shippingMethod
     * @param string $scopeType
     * @param null|int|string|\Magento\Framework\App\ScopeInterface $scopeCode
     * @return int|mixed|string|null
     */
    public function getFulfillmentMethodMapping(
        $shippingMethod,
        $scopeType = ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
        $scopeCode = null
    ) {
        if (isset($shippingMethod) === false) {
            return null;
        }

        $fulfillmentMapping = $this->scopeConfigInterface->getValue(
            'signifyd/advanced/fulfillment_method',
            $scopeType,
            $scopeCode
        );

        if (isset($fulfillmentMapping) === false) {
            return 'DELIVERY';
        }

        try {
            $configMapping = $this->jsonSerializer->unserialize($fulfillmentMapping);
        } catch (\InvalidArgumentException $e) {
            return $fulfillmentMapping;
        }

        foreach ($configMapping as $key => $value) {
            if (in_array($shippingMethod, $value)) {
                return $key;
            }
        }

        return 'DELIVERY';
    }
}
