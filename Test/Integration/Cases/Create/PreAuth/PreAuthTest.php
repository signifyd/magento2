<?php

namespace Signifyd\Connect\Test\Integration\Cases\Create\PreAuth;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Signifyd\Connect\Test\Integration\OrderTestCase;

class PreAuthTest extends OrderTestCase
{
    /**
     * @magentoDataFixture configFixture
     */
    public function testCreatePreAuth()
    {
        list($caseFromQuote, $caseResponse, $quote) = $this->processPreAuth();

        $this->assertNotEmpty($caseFromQuote['checkoutId']);
        $this->assertNotEmpty($caseFromQuote['orderId']);
        $this->assertNotEmpty($caseFromQuote['purchase']);
        $this->assertNotEmpty($caseResponse->signifydId);
        $this->assertNotEmpty($caseResponse->checkoutId);
        $this->assertNotEmpty($caseResponse->orderId);
    }

    public static function configFixture()
    {
        require __DIR__ . '/../../../_files/customer/customer.php';
        require __DIR__ . '/../../../_files/customer/customer_address.php';
        require __DIR__ . '/../../../_files/settings/restrict_none_payment_methods.php';
        require __DIR__ . '/../../../_files/order/guest_quote_with_addresses_product_simple.php';
    }
}