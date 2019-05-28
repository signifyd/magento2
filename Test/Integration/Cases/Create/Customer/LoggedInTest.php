<?php

declare(strict_types=1);

namespace Signifyd\Connect\Test\Integration\Cases\Create\Customer;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Quote\Api\GuestCartManagementInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteIdMask;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Sales\Model\OrderRepository;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Signifyd\Connect\Test\Integration\TestCase;

/**
 * @magentoDbIsolation enabled
 * @magentoAppArea frontend
 */
class LoggedInTest extends TestCase
{
    /**
     * @var QuoteIdMaskFactory
     */
    protected $quoteIdMaskFactory;

    /**
     * @inheritdoc
     */
    protected function setUp()
    {
        parent::setUp();

        $this->quoteIdMaskFactory = $this->objectManager->get(QuoteIdMaskFactory::class);
    }

    /**
     * @magentoDataFixture configFixture
     * @magentoDataFixture Magento/Customer/_files/customer.php
     * @magentoDataFixture Magento/Customer/_files/customer_address.php
     *
     * @return void
     */
    public function testSendCaseLoggedInCustomer(): void
    {
        $orderIncrementId = rand(90000000, 99999999);

        /** @var \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository */
        $customerRepository = $this->objectManager->create(CustomerRepositoryInterface::class);
        $customerId = 1;
        $customer = $customerRepository->getById($customerId);

        /** @var Quote $quote */
        $quote = $this->objectManager->create(Quote::class);
        $quote->load('guest_quote', 'reserved_order_id');
        $quote->setCustomer($customer);
        $quote->setCustomerIsGuest(false);
        $quote->setReservedOrderId($orderIncrementId);
        $quote->save();

        $checkoutSession = $this->objectManager->get(CheckoutSession::class);
        $checkoutSession->setQuoteId($quote->getId());

        /** @var QuoteIdMask $quoteIdMask */
        $quoteIdMask = $this->quoteIdMaskFactory->create();
        $quoteIdMask->load($quote->getId(), 'quote_id');
        $cartId = $quoteIdMask->getMaskedId();

        /** @var GuestCartManagementInterface $cartManagement */
        $cartManagement = $this->objectManager->get(GuestCartManagementInterface::class);
        $orderId = $cartManagement->placeOrder($cartId);

        /** @var \Magento\Sales\Model\Order $order */
        $order = $this->objectManager->get(OrderRepository::class)->get($orderId);

        /** @var \Signifyd\Connect\Model\Casedata $case */
        $case = $this->objectManager->get('\Signifyd\Connect\Model\Casedata');
        $case->load($orderIncrementId);

        $this->assertNotEmpty($order->getCustomerId());
        $this->assertEquals($orderIncrementId, $case->getOrderIncrement());
        $this->assertNotEmpty($case->getCode());
    }

    public static function configFixture()
    {
        require __DIR__ . '/../../../_files/settings/general.php';
        require __DIR__ . '/../../../_files/settings/restrict_none_payment_methods.php';
        require __DIR__ . '/../../../_files/order/guest_quote_with_addresses_product_simple.php';
    }
}


