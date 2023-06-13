<?php

namespace Signifyd\Connect\Model\Api;

class RecordReturn
{
    /**
     * @var DeviceFactory
     */
    protected $deviceFactory;

    /**
     * @var ProductFactory
     */
    protected $productFactory;

    /**
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
     * @param $order
     * @return array
     */
    public function __invoke($order)
    {
        $recordReturn = [];
        $device = $this->deviceFactory->create();
        $items = $order->getAllItems();
        $recordReturn['returnedItems'] = [];

        /** @var \Magento\Sales\Model\Order\Item $item */
        foreach ($items as $item) {
            $children = $item->getChildrenItems();

            if (is_array($children) == false || empty($children)) {
                $makeProduct = $this->productFactory->create();
                $productToReturn = $makeProduct($item);
                $productToReturn['reason'] = 'CANCELATION';
                $recordReturn['returnedItems'][] = $productToReturn;
            }
        }

        $recordReturn['orderId'] = $order->getIncrementId();
        $recordReturn['returnId'] = uniqid();
        $recordReturn['device'] = $device($order->getQuoteId(), $order->getStoreId(), $order);

        return $recordReturn;
    }
}