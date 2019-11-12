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
        /** @var \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository */
        $customerRepository = $this->objectManager->create(CustomerRepositoryInterface::class);
        $customer = $customerRepository->getById(1);

        /** @var \Magento\Quote\Model\Quote $quote */
        $quote = $this->getQuote('guest_quote');
        $quote->setCustomerIsGuest(false);
        $quote->assignCustomer($customer);
        $quote->save();

        $order = $this->placeQuote($quote);
        $case = $this->getCase();

        $this->assertNotEmpty($order->getCustomerId());
        $this->assertEquals($this->incrementId, $case->getOrderIncrement());
        $this->assertNotEmpty($case->getCode());
    }

    public static function configFixture()
    {
        require __DIR__ . '/../../../_files/customer/customer.php';
        require __DIR__ . '/../../../_files/customer/customer_address.php';
        require __DIR__ . '/../../../_files/settings/restrict_none_payment_methods.php';
        require __DIR__ . '/../../../_files/order/guest_quote_with_addresses_product_simple.php';
    }
}
