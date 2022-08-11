<?php

namespace Signifyd\Connect\Test\Integration\Cases\Create\PreAuth;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Signifyd\Connect\Test\Integration\OrderTestCase;

class TransactionTest extends OrderTestCase
{
    /**
     * @magentoDataFixture configFixture
     */
    public function testCreateTransaction()
    {
        list($caseFromQuote, $caseResponse, $quote) = $this->processPreAuth();
        
        $order = $this->placeQuote($quote);
        $tokenSent = $caseFromQuote['checkoutId'];

        //Send transaction to pre auth case after order is placed
        $saleTransaction = [];
        $saleTransaction['checkoutId'] = $tokenSent;
        $saleTransaction['orderId'] = $caseFromQuote['orderId'];
        $saleTransaction['transactions'] = $this->purchaseHelper->makeTransactions($order);

        $transactionResponse = $this->purchaseHelper->postTransactionToSignifyd($saleTransaction, $order);
        $tokenReceived = $transactionResponse->getCheckoutId();
        
        $this->assertNotEmpty($caseFromQuote['checkoutId']);
        $this->assertEquals($tokenSent, $tokenReceived);
    }

    public static function configFixture()
    {
        require __DIR__ . '/../../../_files/customer/customer.php';
        require __DIR__ . '/../../../_files/customer/customer_address.php';
        require __DIR__ . '/../../../_files/settings/restrict_none_payment_methods.php';
        require __DIR__ . '/../../../_files/order/guest_quote_with_addresses_product_simple.php';
    }
}