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
class PassiveMode extends OrderTestCase
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
        $writerInterface->save('signifyd/general/enabled', 'passive');

        $order = parent::createOrderCustomerLoggedIn();
        $histories = $order->getStatusHistories();

        foreach ($histories as $history) {
            $this->assertStringContainsString('PASSIVE', $history->getComment());
        }

        $case = $this->getCase();
        $writerInterface->delete('signifyd/general/enabled');

        $this->assertNotEmpty($case->getCode());
        $this->assertEquals($case->getPolicyName(), 'post_auth');
    }

    public static function configFixture()
    {
        require __DIR__ . '/../_files/customer/customer.php';
        require __DIR__ . '/../_files/customer/customer_address.php';
        require __DIR__ . '/../_files/settings/restrict_none_payment_methods.php';
        require __DIR__ . '/../_files/order/guest_quote_with_addresses_product_simple.php';
    }
}
