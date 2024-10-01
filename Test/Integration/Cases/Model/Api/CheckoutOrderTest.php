<?php

namespace Signifyd\Connect\Test\Integration\Cases\Model\Api;

use Signifyd\Connect\Test\Integration\Cases\Cron\CreateTest;

class CheckoutOrderTest extends CreateTest
{
    public function testCronCreateCase()
    {
        // Bypassing test
    }

    /**
     * @magentoDataFixture configFixture
     */
    public function testCheckoutOrder()
    {
        $checkoutOrder = $this->objectManager->create(\Signifyd\Connect\Model\Api\CheckoutOrder::class);

        $checkoutOrderData = $checkoutOrder($this->getQuote('guest_quote'));

        //validate required fields
        $this->assertTrue(isset($checkoutOrderData['checkoutId']));
        $this->assertTrue(isset($checkoutOrderData['orderId']));
        $this->assertTrue(isset($checkoutOrderData['purchase']));
    }
}
