<?php

declare(strict_types=1);

namespace Signifyd\Connect\Test\Integration\Cases\Create\Customer;

use Signifyd\Connect\Test\Integration\OrderTestCase;

/**
 * @magentoDbIsolation enabled
 * @magentoAppArea frontend
 */
class GuestTest extends OrderTestCase
{
    /**
     * @magentoDataFixture configFixture
     *
     * @return void
     */
    public function testSendCaseGuestCustomer()
    {
        $order = $this->placeQuote($this->getQuote('guest_quote'));

        $case = $this->getCase();

        $this->assertEquals($this->incrementId, $case->getOrderIncrement());
        $this->assertEmpty($order->getCustomerId());
        $this->assertEquals('banktransfer', $order->getPayment()->getMethod());
        $this->assertNotEmpty($case->getCode());
    }

    public static function configFixture()
    {
        require __DIR__ . '/../../../_files/settings/restrict_none_payment_methods.php';
        require __DIR__ . '/../../../_files/order/guest_quote_with_addresses_product_simple.php';
    }
}
