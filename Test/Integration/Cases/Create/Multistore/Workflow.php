<?php

declare(strict_types=1);

namespace Signifyd\Connect\Test\Integration\Cases\Create\Multistore;

use Signifyd\Connect\Test\Integration\OrderTestCase;

/**
 * @magentoDbIsolation enabled
 * @magentoAppArea frontend
 */
class Workflow extends OrderTestCase
{
    /**
     * @magentoDataFixture configFixture
     *
     * @return void
     */
    public function testSendCaseMultistoreWorkflow()
    {
        $defaultStoreIncrementId = $this->incrementId = '10-' . time();
        $alternateStoreIncrementId = $this->incrementId = '40-' . time();

        $alternateStoreOrder = $this->placeQuote($this->getQuote('guest_quote_alt', $alternateStoreIncrementId));
        $defaultStoreOrder = $this->placeQuote($this->getQuote('guest_quote', $defaultStoreIncrementId));

        $defaultStoreCase = $this->getCase($defaultStoreIncrementId);
        $alternateStoreCase = $this->getCase($alternateStoreIncrementId);

        $this->assertEquals($defaultStoreIncrementId, $defaultStoreCase->getOrderIncrement());
        $this->assertEquals($alternateStoreIncrementId, $alternateStoreCase->getOrderIncrement());

        $this->assertNotEmpty($defaultStoreCase->getCode());
        $this->assertNotEmpty($alternateStoreCase->getCode());

        $this->assertNotEquals($alternateStoreOrder->getStoreId(), $defaultStoreOrder->getStoreId());

        $this->assertEquals('new', $defaultStoreOrder->getState());
        $this->assertEquals('holded', $alternateStoreOrder->getState());
    }

    public static function configFixture()
    {
        require __DIR__ . '/../../../_files/multistore/core_fixturestore.php';
        require __DIR__ . '/../../../_files/settings/multistore/workflow.php';
        require __DIR__ . '/../../../_files/settings/restrict_none_payment_methods.php';
        require __DIR__ . '/../../../_files/order/guest_quote_with_addresses_product_simple.php';
        require __DIR__ . '/../../../_files/order/guest_quote_with_addresses_product_simple_alternate_store.php';
    }
}
