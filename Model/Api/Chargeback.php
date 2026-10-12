<?php

namespace Signifyd\Connect\Model\Api;

use Magento\Sales\Model\Order;
use Magento\Store\Model\ScopeInterface;
use Signifyd\Connect\Helper\FulfillmentHelper;

class Chargeback
{
    /**
     * @var ChargebackCreateRequestFactory
     */
    public $chargebackCreateRequestFactory;

    /**
     * @var Shipments
     */
    public $shipments;

    /**
     * @var CarrierFactory
     */
    public $carrierFactory;

    /**
     * @var FulfillmentHelper
     */
    public $fulfillmentHelper;

    /**
     * Chargeback construct.
     *
     * @param ChargebackCreateRequestFactory $chargebackCreateRequestFactory
     * @param Shipments $shipments
     * @param CarrierFactory $carrierFactory
     * @param FulfillmentHelper $fulfillmentHelper
     */
    public function __construct(
        ChargebackCreateRequestFactory $chargebackCreateRequestFactory,
        Shipments $shipments,
        CarrierFactory $carrierFactory,
        FulfillmentHelper $fulfillmentHelper
    )
    {
        $this->chargebackCreateRequestFactory = $chargebackCreateRequestFactory;
        $this->shipments = $shipments;
        $this->carrierFactory = $carrierFactory;
        $this->fulfillmentHelper = $fulfillmentHelper;
    }

    /**
     * Construct a new Chargeback object
     *
     * @param Order $order
     * @param array $chargebackData
     * @return []
     */
    public function __invoke(Order $order, array $chargebackData)
    {
        $chargeback = [];
        $chargeback['orderId'] = $order->getIncrementId();
        $chargeback['chargebackId'] = (string) ($chargebackData['chargebackId'] ?? '');
        $chargeback['chargeback'] = ($this->chargebackCreateRequestFactory->create())($order, $chargebackData);

        if (empty($chargebackData['partnerChargebackId']) === false) {
            $chargeback['partnerChargebackId'] = $chargebackData['partnerChargebackId'];
        }

        if (empty($chargebackData['partnerMerchantId']) === false) {
            $chargeback['partnerMerchantId'] = $chargebackData['partnerMerchantId'];
        }

        $fulfillments = [];
        $shipper = ($this->carrierFactory->create())($order->getShippingMethod());
        $fulfillmentMethod = $this->shipments->getFulfillmentMethodMapping(
            $order->getShippingMethod(),
            ScopeInterface::SCOPE_STORES,
            $order->getStoreId()
        );

        foreach ($order->getShipmentsCollection() as $shipment) {
            /**
             * A fulfillment carries a single tracking number, so a shipment holding more than one
             * is reported as one fulfillment for each of them, instead of only the first one.
             */
            foreach ($this->fulfillmentHelper->getTrackingNumbers($shipment) as $trackingNumber) {
                $fulfillment = [];
                $fulfillment['shipper'] = $shipper;
                $fulfillment['trackingNumber'] = $trackingNumber;
                $fulfillment['fulfillmentMethod'] = $fulfillmentMethod;

                $fulfillments[] = $fulfillment;
            }
        }

        // The field is optional, so it is left out when the order has no tracking numbers at all
        if (empty($fulfillments) === false) {
            $chargeback['fulfillments'] = $fulfillments;
        }

        return $chargeback;
    }
}
