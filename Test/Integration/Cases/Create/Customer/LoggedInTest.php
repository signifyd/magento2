<?php

declare(strict_types=1);

namespace Signifyd\Connect\Test\Integration\Cases\Create\Customer;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Signifyd\Connect\Test\Integration\OrderTestCase;

/**
 * @magentoDbIsolation enabled
 * @magentoAppArea frontend
 */
class LoggedInTest extends OrderTestCase
{
    /**
     * @magentoDataFixture configFixture
     *
     * @return void
     */
    public function testSendCaseLoggedInCustomer()
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order = parent::createOrderCustomerLoggedIn();
        $case = $this->getCase();

        $this->assertNotEmpty($order->getCustomerId());
        $this->assertEquals($this->incrementId, $case->getOrderIncrement());
        $this->assertNotEmpty($case->getCode());
        $this->assertEquals($case->getPolicyName(), 'post_auth');
    }

    public static function configFixture()
    {
        require __DIR__ . '/../../../_files/customer/customer.php';
        require __DIR__ . '/../../../_files/customer/customer_address.php';
        require __DIR__ . '/../../../_files/settings/restrict_none_payment_methods.php';
        require __DIR__ . '/../../../_files/order/guest_quote_with_addresses_product_simple.php';
    }
}
