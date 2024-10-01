<?php

namespace Signifyd\Connect\Test\Integration\Cases\Model\Api;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Signifyd\Connect\Test\Integration\Cases\Cron\CreateTest;

class UserAccountTest extends CreateTest
{
    public function testCronCreateCase()
    {
        // Bypassing test
    }

    /**
     * @magentoDataFixture configFixture
     */
    public function testUserAccountTest()
    {
        $userAccount = $this->objectManager->create(\Signifyd\Connect\Model\Api\UserAccount::class);
        $customerRepository = $this->objectManager->create(CustomerRepositoryInterface::class);
        $customer = $customerRepository->getById(1);

        /** @var \Magento\Quote\Model\Quote $quote */
        $quote = $this->getQuote('guest_quote');
        $quote->setCustomerIsGuest(false);
        $quote->assignCustomer($customer);
        $quote->save();

        $order = $this->placeQuote($quote);
        $userAccountData = $userAccount($order);

        //validate required fields
        $this->assertTrue(isset($userAccountData['username']));
        $this->assertTrue(isset($userAccountData['createdDate']));
        $this->assertTrue(isset($userAccountData['accountNumber']));
        $this->assertTrue(isset($userAccountData['email']));
        $this->assertTrue(isset($userAccountData['phone']));
        $this->assertTrue(isset($userAccountData['lastUpdateDate']));
    }

    public static function configFixture()
    {
        require __DIR__ . '/../../../_files/customer/customer.php';
        require __DIR__ . '/../../../_files/customer/customer_address.php';
        require __DIR__ . '/../../../_files/settings/restrict_none_payment_methods.php';
        require __DIR__ . '/../../../_files/order/guest_quote_with_addresses_product_simple.php';
    }
}
