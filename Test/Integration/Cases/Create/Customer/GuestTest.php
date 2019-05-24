<?php

declare(strict_types=1);

namespace Test\Integration\Cases\Create\Customer;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Quote\Api\GuestCartManagementInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteIdMask;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Sales\Model\OrderRepository;
use Signifyd\Connect\Test\Integration\TestCase;

/**
 * @magentoDbIsolation enabled
 * @magentoAppArea frontend
 */
class GuestTest extends TestCase
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
     *
     * @return void
     */
    public function testSendCaseGuestCustomer(): void
    {
        $orderIncrementId = rand(90000000, 99999999);

        /** @var Quote $quote */
        $quote = $this->objectManager->create(Quote::class);
        $quote->load('guest_quote', 'reserved_order_id');
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

        $this->assertEmpty($order->getCustomerId());
        $this->assertEquals($case->getOrderIncrement(), $orderIncrementId);
        $this->assertNotEmpty($case->getCode());

    }

    public static function configFixture()
    {
        require __DIR__ . '/../../../_files/settings/restrict_none_payment_methods.php';
        require __DIR__ . '/../../../_files/order/guest_quote_with_addresses_product_simple.php';
    }
}


