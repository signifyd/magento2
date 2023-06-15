<?php

namespace Signifyd\Connect\Model\Api;

use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;

class Fulfillments
{

    /**
     * @var JsonSerializer
     */
    protected $jsonSerializer;

    /**
     * @param JsonSerializer $jsonSerializer
     */
    public function __construct(
        JsonSerializer $jsonSerializer
    ) {
        $this->jsonSerializer = $jsonSerializer;
    }

    /**
     * Construct a new Fulfillments object
     * @param $fulfillmentData
     * @return array
     */
    public function __invoke($fulfillmentData)
    {
        $fulfillments = [];
        $fulfillment = [];
        $fulfillment['shipmentId'] = $fulfillmentData->getData('id');
        $fulfillment['shippedAt'] = $fulfillmentData->getData('shipped_at');
        $fulfillment['products'] = $this->jsonSerializer->unserialize($fulfillmentData->getProducts());
        $fulfillment['shipmentStatus'] = $fulfillmentData->getData('shipment_status');
        $fulfillment['trackingUrls'] = $fulfillmentData->getData('tracking_urls');
        $fulfillment['trackingNumbers'] = $fulfillmentData->getData('tracking_numbers');
        $fulfillment['destination'] = $this->jsonSerializer->unserialize($fulfillmentData->getData('destination'));
        $fulfillment['origin'] = $this->jsonSerializer->unserialize($fulfillmentData->getData('origin'));
        $fulfillment['carrier'] = $fulfillmentData->getData('carrier');

        $fulfillments[] = $fulfillment;
        return $fulfillments;
    }
}
