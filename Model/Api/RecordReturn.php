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
     * @var ReturnTagsFactory
     */
    public $returnTagsFactory;

    /**
     * @var ReturnDateFactory
     */
    public $returnDateFactory;

    /**
     * @var ReturnStatusFactory
     */
    public $returnStatusFactory;

    /**
     * @var ReturnSubReasonFactory
     */
    public $returnSubReasonFactory;

    /**
     * RecordReturn construct.
     *
     * @param DeviceFactory $deviceFactory
     * @param ProductFactory $productFactory
     * @param ReturnTagsFactory $returnTagsFactory
     * @param ReturnDateFactory $returnDateFactory
     * @param ReturnStatusFactory $returnStatusFactory
     * @param ReturnSubReasonFactory $returnSubReasonFactory
     */
    public function __construct(
        DeviceFactory $deviceFactory,
        ProductFactory $productFactory,
        ReturnTagsFactory $returnTagsFactory,
        ReturnDateFactory $returnDateFactory,
        ReturnStatusFactory $returnStatusFactory,
        ReturnSubReasonFactory $returnSubReasonFactory
    ) {
        $this->deviceFactory = $deviceFactory;
        $this->productFactory = $productFactory;
        $this->returnTagsFactory = $returnTagsFactory;
        $this->returnDateFactory = $returnDateFactory;
        $this->returnStatusFactory = $returnStatusFactory;
        $this->returnSubReasonFactory = $returnSubReasonFactory;
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
                $productToReturn['subReason'] = ($this->returnSubReasonFactory->create())();
                $recordReturn['returnedItems'][] = $productToReturn;
            }
        }

        $recordReturn['orderId'] = $order->getIncrementId();
        $recordReturn['returnStatus'] = ($this->returnStatusFactory->create())();
        $recordReturn['returnDate'] = ($this->returnDateFactory->create())();
        $recordReturn['tags'] = ($this->returnTagsFactory->create())();
        $recordReturn['returnId'] = uniqid();
        $recordReturn['device'] = $device($order->getQuoteId(), $order->getStoreId(), $order);

        $recordReturn['refund']['amount'] = $order->getGrandTotal();
        $recordReturn['refund']['currency'] = $order->getOrderCurrencyCode();
        $recordReturn['refund']['method'] = 'ORIGINAL_PAYMENT_INSTRUMENT';

        return $recordReturn;
    }
}
