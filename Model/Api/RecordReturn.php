<?php

namespace Signifyd\Connect\Model\Api;

class RecordReturn
{
    /**
     * @var DeviceFactory
     */
    public $deviceFactory;

    /**
     * @var ProductFactory
     */
    public $productFactory;

    /**
     * RecordReturn construct.
     *
     * @param DeviceFactory $deviceFactory
     * @param ProductFactory $productFactory
     */
    public function __construct(
        DeviceFactory $deviceFactory,
        ProductFactory $productFactory
    ) {
        $this->deviceFactory = $deviceFactory;
        $this->productFactory = $productFactory;
    }

    /**
     * Construct a new RecordReturn object
     *
     * @param \Magento\Sales\Model\Order $order
     * @return array
     */
    public function __invoke($order)
    {
        $recordReturn = [];
        $items = $order->getAllItems();
        $recordReturn['returnedItems'] = [];

        /** @var \Magento\Sales\Model\Order\Item $item */
        foreach ($items as $item) {
            $children = $item->getChildrenItems();

            if (is_array($children) == false || empty($children)) {
                $productToReturn = ($this->productFactory->create())($item);
                $productToReturn['reason'] = 'CANCELATION';
                $recordReturn['returnedItems'][] = $productToReturn;
            }
        }

        $recordReturn['orderId'] = $order->getIncrementId();
        $recordReturn['returnId'] = uniqid();
        $recordReturn['device'] = ($this->deviceFactory->create())($order->getQuoteId(), $order->getStoreId(), $order);

        $recordReturn['refund']['amount'] = $order->getGrandTotal();
        $recordReturn['refund']['currency'] = $order->getOrderCurrencyCode();
        $recordReturn['refund']['method'] = 'ORIGINAL_PAYMENT_INSTRUMENT';

        return $recordReturn;
    }
}
