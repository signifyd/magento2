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

        foreach ($order->getShipmentsCollection() as $shipment) {
            $trackingNumbers = $this->fulfillmentHelper->getTrackingNumbers($shipment);

            if (empty($trackingNumbers)) {
                continue;
            }

            $fulfillment = [];
            $fulfillment['shipper'] = ($this->carrierFactory->create())($order->getShippingMethod());
            $fulfillment['trackingNumber'] = $trackingNumbers[0];
            $fulfillment['fulfillmentMethod'] = $this->shipments->getFulfillmentMethodMapping(
                $order->getShippingMethod(),
                ScopeInterface::SCOPE_STORES,
                $order->getStoreId()
            );

            $fulfillments[] = $fulfillment;
        }

        $chargeback['fulfillments'] = empty($fulfillments) ? null : $fulfillments;
        
        return $chargeback;
    }
}
