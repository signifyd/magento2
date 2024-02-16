<?php

namespace Signifyd\Connect\Test\Integration\Cases\Cron;

use Signifyd\Connect\Test\Integration\OrderTestCase;

class RetryFulfillmentJob extends OrderTestCase
{
    /**
     * @var \Signifyd\Connect\Cron\RetryFulfillmentJob
     */
    public $retryFulfillmentJob;

    public function setUp(): void
    {
        parent::setUp();

        $this->retryFulfillmentJob = $this->objectManager->create(\Signifyd\Connect\Cron\RetryFulfillmentJob::class);
    }

    /**
     * @magentoDataFixture configFixture
     */
    public function testRetryFulfillment()
    {
        $order = $this->placeQuote($this->getQuote('guest_quote'));

        try {
            $this->createShipment($order);
        } catch (\Exception $e) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __($e->getMessage())
            );
        }

        //Modifying inserted_at value due to cron rules for retries. The seconds_after_inserted_at need to be greater
        // than 20 sec.
        $this->getFulfillment($this->incrementId)->setData('inserted_at', date('Y-m-d H:i:s', time() - 21))->save();

        //Waiting to signifyd process the case
        sleep(10);

        $this->retryFulfillmentJob->execute();
        $fulfillment = $this->getFulfillment($this->incrementId);

        $this->assertEquals('completed', $fulfillment->getData('magento_status'));
        $this->assertEquals('COMPLETE', $fulfillment->getData('fulfillment_status'));
        $this->assertNotEmpty($fulfillment->getData('order_id'));
    }

    public static function configFixture()
    {
        require __DIR__ . '/../../_files/order/guest_quote_with_addresses_product_simple.php';
    }

    public function createShipment(\Magento\Sales\Model\Order $order)
    {
        if (!$order->canShip()) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __('You can\'t create an shipment.')
            );
        }

        $convertOrder = $this->objectManager->create('Magento\Sales\Model\Convert\Order');
        /** @var \Magento\Sales\Model\Order\Shipment $shipment */
        $shipment = $convertOrder->toShipment($order);

        foreach ($order->getAllItems() as $orderItem) {
            // Check if order item has qty to ship or is virtual
            if (! $orderItem->getQtyToShip() || $orderItem->getIsVirtual()) {
                continue;
            }

            $qtyShipped = $orderItem->getQtyToShip();
            $shipmentItem = $convertOrder->itemToShipmentItem($orderItem)->setQty($qtyShipped);
            $shipment->addItem($shipmentItem);
        }

        $shipment->register();
        $shipment->getOrder()->setIsInProcess(true);

        try {
            $shipment->save();
            $shipment->getOrder()->save();

            /** @var \Magento\Sales\Model\Order\Shipment\Track $track */
            $track = $this->objectManager->create('Magento\Sales\Model\Order\Shipment\Track');
            $track->setNumber(1111);
            $track->setCarrierCode('testcarrier');
            $track->setTitle('Test Carrier');

            $shipment->addTrack($track);
            $shipment->save();
        } catch (\Exception $e) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __($e->getMessage())
            );
        }
    }
}
