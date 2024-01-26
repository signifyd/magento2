<?php

namespace Signifyd\Connect\Test\Integration\Cases\Update;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Signifyd\Connect\Test\Integration\OrderTestCase;

class OrderAddressTest extends OrderTestCase
{
    /**
     * @var \Magento\Sales\Model\Order\AddressRepository
     */
    public $addressRepository;

    /**
     * @var \Magento\Sales\Model\OrderRepository
     */
    public $orderRepository;

    public function setUp(): void
    {
        parent::setUp();

        $this->addressRepository = $this->objectManager->create(\Magento\Sales\Model\Order\AddressRepository::class);
        $this->orderRepository = $this->objectManager->create(\Magento\Sales\Model\OrderRepository::class);
        $this->shipmentsFactory = $this->objectManager->create(\Signifyd\Connect\Model\Api\ShipmentsFactory::class);
        $this->client = $this->objectManager->create(\Signifyd\Connect\Model\Api\Core\Client::class);
        $this->deviceFactory = $this->objectManager->create(\Signifyd\Connect\Model\Api\DeviceFactory::class);
    }

    /**
     * @magentoDataFixture configFixture
     */
    public function testCreateReroute()
    {
        $order = $this->placeQuote($this->getQuote('guest_quote'));

        //Waiting to signifyd process the case
        sleep(10);

        $shipAddress = $this->addressRepository->get($order->getShippingAddress()->getId());

        if ($shipAddress->getId()) {
            $shipAddress->setFirstname('Michael');
            $shipAddress->setCompany('Company Name');
            $this->addressRepository->save($shipAddress);
        }

        $updatedOrder = $this->orderRepository->get($order->getId());
        $makeShipments = $this->shipmentsFactory->create();
        $shipments = $makeShipments($updatedOrder);
        $device = $this->deviceFactory->create();

        $rerout = [];
        $rerout['orderId'] = $updatedOrder->getIncrementId();
        $rerout['device'] = $device($updatedOrder->getQuoteId(), $updatedOrder->getStoreId());
        $rerout['shipments'] = $shipments;

        $updateResponse = $this->client->createReroute($rerout, $updatedOrder);

        $this->assertNotEmpty($updateResponse->decision);
        $this->assertNotEmpty($updateResponse->decision->checkpointAction);
        $this->assertNotEmpty($updateResponse->decision->score);
    }

    public static function configFixture()
    {
        require __DIR__ . '/../../_files/settings/restrict_none_payment_methods.php';
        require __DIR__ . '/../../_files/order/guest_quote_with_addresses_product_simple.php';
    }
}
