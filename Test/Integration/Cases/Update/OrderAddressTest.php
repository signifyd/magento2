<?php

namespace Signifyd\Connect\Test\Integration\Cases\Update;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Signifyd\Connect\Test\Integration\OrderTestCase;

class OrderAddressTest extends OrderTestCase
{
    /**
     * @var \Magento\Sales\Model\Order\AddressRepository
     */
    protected $addressRepository;

    /**
     * @var \Magento\Sales\Model\OrderRepository
     */
    protected $orderRepository;

    /**
     * @var \Signifyd\Connect\Helper\PurchaseHelper
     */
    protected $purchaseHelper;

    public function setUp(): void
    {
        parent::setUp();

        $this->addressRepository = $this->objectManager->create(\Magento\Sales\Model\Order\AddressRepository::class);
        $this->orderRepository = $this->objectManager->create(\Magento\Sales\Model\OrderRepository::class);
        $this->purchaseHelper = $this->objectManager->create(\Signifyd\Connect\Helper\PurchaseHelper::class);
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
        $shipments = $this->purchaseHelper->makeShipments($updatedOrder);

        $rerout = [];
        $rerout['orderId'] = $updatedOrder->getIncrementId();
        $rerout['device'] = $this->purchaseHelper->makeDevice($updatedOrder->getQuoteId(), $updatedOrder->getStoreId());
        $rerout['shipments'] = $shipments;

        $updateResponse = $this->purchaseHelper->createReroute($rerout, $updatedOrder);

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