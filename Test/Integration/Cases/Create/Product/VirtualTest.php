<?php

declare(strict_types=1);

namespace Signifyd\Connect\Test\Integration\Cases\Create\Product;

use Signifyd\Connect\Test\Integration\OrderTestCase;

/**
 * @magentoDbIsolation enabled
 * @magentoAppArea frontend
 */
class VirtualTest extends OrderTestCase
{
    /**
     * @magentoDataFixture configFixture
     *
     * @return void
     */
    public function testSendCaseVirtualProduct()
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order = $this->placeQuote($this->getQuote('guest_quote'));
        $case = $this->getCase();

        $allVirtual = true;

        foreach ($order->getAllItems() as $item) {
            if ($item->getProductType() !== 'virtual') {
                $allVirtual = false;
            }
        }

        $this->assertEmpty($order->getCustomerId());
        $this->assertEquals($this->incrementId, $case->getOrderIncrement());
        $this->assertNotEmpty($case->getCode());
        $this->assertEquals(true, $allVirtual);
    }

    public static function configFixture()
    {
        require __DIR__ . '/../../../_files/settings/restrict_none_payment_methods.php';
        require __DIR__ . '/../../../_files/order/guest_quote_with_addresses_product_virtual.php';
    }
}
