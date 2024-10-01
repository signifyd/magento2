<?php

namespace Signifyd\Connect\Test\Integration\Cases\Model\Api;

use Signifyd\Connect\Test\Integration\Cases\Cron\CreateTest;

class PurchaseTest extends CreateTest
{
    public function testCronCreateCase()
    {
        // Bypassing test
    }

    /**
     * @magentoDataFixture configFixture
     */
    public function testSaleOrder()
    {
        $purchase = $this->objectManager->create(\Signifyd\Connect\Model\Api\Purchase::class);

        $order = $this->placeQuote($this->getQuote('guest_quote'));
        $purchaseData = $purchase($order);

        //validate required fields
        $this->assertTrue(isset($purchaseData['createdAt']));
        $this->assertTrue(isset($purchaseData['orderChannel']));
        $this->assertTrue(isset($purchaseData['totalPrice']));
        $this->assertTrue(isset($purchaseData['confirmationEmail']));
        $this->assertTrue(isset($purchaseData['products']));
        $this->assertTrue(isset($purchaseData['shipments']));
        $this->assertTrue(isset($purchaseData['confirmationPhone']));
        $this->assertTrue(isset($purchaseData['totalShippingCost']));
    }
}
