<?php

declare(strict_types=1);

namespace Signifyd\Connect\Test\Integration\Cases;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Signifyd\Connect\Test\Integration\OrderTestCase;
use Magento\Framework\App\Config\Storage\WriterInterface;

/**
 * @magentoDbIsolation enabled
 * @magentoAppArea frontend
 */
class PreAuth extends OrderTestCase
{
    /**
     * @magentoDataFixture configFixture
     *
     * @return void
     */
    public function testSendCaseLoggedInCustomer()
    {
        /** @var \Magento\Framework\App\Config\Storage\WriterInterface $writerInterface */
        $writerInterface = $this->objectManager->create(WriterInterface::class);
        $writerInterface->save('signifyd/advanced/policy_name', 'PRE_AUTH');

        $order = parent::createOrderCustomerLoggedIn();
        $case = $this->getCase();
        $writerInterface->delete('signifyd/advanced/policy_name');

        $this->assertNotEmpty($order->getCustomerId());
        $this->assertEquals($this->incrementId, $case->getOrderIncrement());
        $this->assertNotEmpty($case->getCode());
        $this->assertEquals($case->getPolicyName(), 'pre_auth');
    }

    public static function configFixture()
    {
        require __DIR__ . '/../_files/customer/customer.php';
        require __DIR__ . '/../_files/customer/customer_address.php';
        require __DIR__ . '/../_files/settings/restrict_none_payment_methods.php';
        require __DIR__ . '/../_files/order/guest_quote_with_addresses_product_simple.php';
    }
}
