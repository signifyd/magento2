<?php

namespace Signifyd\Connect\Test\Integration\Cases\Model\Api;

use Signifyd\Connect\Test\Integration\Cases\Cron\CreateTest;

class ProductTest extends CreateTest
{
    public function testCronCreateCase()
    {
        // Bypassing test
    }

    /**
     * @magentoDataFixture configFixture
     */
    public function testProduct()
    {
        $product = $this->objectManager->create(\Signifyd\Connect\Model\Api\Product::class);

        $order = $this->placeQuote($this->getQuote('guest_quote'));
        $productData = $product($order->getItems()[0]);

        //validate required fields
        $this->assertTrue(isset($productData['itemName']));
        $this->assertTrue(isset($productData['itemPrice']));
        $this->assertTrue(isset($productData['itemQuantity']));
        $this->assertTrue(isset($productData['itemId']));
    }
}
