<?php

namespace Signifyd\Connect\Model\Api;

class Fulfillment
{

    /**
     * @var FulfillmentsFactory
     */
    protected $fulfillmentsFactory;

    /**
     * @param FulfillmentsFactory $fulfillmentsFactory
     */
    public function __construct(
        FulfillmentsFactory $fulfillmentsFactory
    ) {
        $this->fulfillmentsFactory = $fulfillmentsFactory;
    }

    /**
     * Construct a new Fulfillment object
     * @param $fulfillment
     * @return array
     */
    public function __invoke($fulfillment)
    {
        $fulfillments = $this->fulfillmentsFactory->create();

        $fulfillmentData = [];
        $fulfillmentData['orderId'] = $fulfillment->getData('order_id');
        $fulfillmentData['fulfillmentStatus'] = $fulfillment->getData('fulfillment_status');
        $fulfillmentData['fulfillments'] = $fulfillments($fulfillment);

        return $fulfillmentData;
    }
}
