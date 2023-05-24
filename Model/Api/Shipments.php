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
    protected $scopeConfigInterface;

    /**
     * @var JsonSerializer
     */
    protected $jsonSerializer;

    /**
     * @var CarrierFactory
     */
    protected $carrierFactory;

    /**
     * @var RecipientFactory
     */
    protected $recipientFactory;

    /**
     * @var OriginFactory
     */
    protected $originFactory;

    /**
     * @param ScopeConfigInterface $scopeConfigInterface
     * @param JsonSerializer $jsonSerializer
     * @param CarrierFactory $carrierFactory
     * @param RecipientFactory $recipientFactory
     * @param OriginFactory $originFactory
     */
    public function __construct(
        ScopeConfigInterface $scopeConfigInterface,
        JsonSerializer $jsonSerializer,
        CarrierFactory $carrierFactory,
        RecipientFactory $recipientFactory,
        OriginFactory $originFactory
    ) {
        $this->scopeConfigInterface = $scopeConfigInterface;
        $this->jsonSerializer = $jsonSerializer;
        $this->carrierFactory = $carrierFactory;
        $this->recipientFactory = $recipientFactory;
        $this->originFactory = $originFactory;
    }

    /**
     * Construct a new Shipment object
     * @param $entity Order|Quote
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
     * @param Order $order
     * @return array
     */
    protected function makeShipments(Order $order)
    {
        $shipments = [];
        $shippingMethod = $order->getShippingMethod(true);
        $carrier = $this->carrierFactory->create();
        $recipientFactory = $this->recipientFactory->create();
        $originFactory = $this->originFactory->create();

        $shipment = [];
        $shipment['destination'] = $recipientFactory($order);
        $shipment['origin'] = $originFactory($order->getStoreId());
        $shipment['carrier'] = $carrier($shippingMethod);
        //TODO: RESOLVER
        $shipment['minDeliveryDate'] = $this->makeMinDeliveryDate();
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
     * @param Quote $quote
     * @return array
     */
    protected function makeShipmentsFromQuote(Quote $quote)
    {
        $shipments = [];
        $shippingMethod = $quote->getShippingAddress()->getShippingMethod();
        $carrier = $this->carrierFactory->create();
        $recipientFactory = $this->recipientFactory->create();
        $originFactory = $this->originFactory->create();

        $shipment = [];
        $shipment['destination'] = $recipientFactory($quote);
        $shipment['origin'] = $originFactory($quote->getStoreId());
        $shipment['carrier'] = $carrier($shippingMethod);
        //TODO: RESOLVER
        $shipment['minDeliveryDate'] = $this->makeMinDeliveryDate();
        $shipment['maxDeliveryDate'] = null;
        $shipment['shipmentId'] = null;
        $shipment['fulfillmentMethod'] = $this->getFulfillmentMethodMapping(
            $quote->getShippingMethod(),
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

    protected function getFulfillmentMethodMapping(
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