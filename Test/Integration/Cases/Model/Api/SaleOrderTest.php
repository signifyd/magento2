<?php

namespace Signifyd\Connect\Test\Integration\Cases\Model\Api;

use Signifyd\Connect\Test\Integration\Cases\Cron\CreateTest;

class SaleOrderTest extends CreateTest
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
        $saleOrder = $this->objectManager->create(\Signifyd\Connect\Model\Api\SaleOrder::class);

        $order = $this->placeQuote($this->getQuote('guest_quote'));
        $saleOrderData = $saleOrder($order);

        //validate required fields
        $this->assertTrue(isset($saleOrderData['orderId']));
        $this->assertTrue(isset($saleOrderData['purchase']));
        $this->assertTrue(isset($saleOrderData['coverageRequests']));
    }
}
