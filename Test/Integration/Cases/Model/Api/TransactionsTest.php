<?php

namespace Signifyd\Connect\Test\Integration\Cases\Model\Api;

use Signifyd\Connect\Test\Integration\Cases\Cron\CreateTest;

class TransactionsTest extends CreateTest
{
    public function testCronCreateCase()
    {
        // Bypassing test
    }

    /**
     * @magentoDataFixture configFixture
     */
    public function testSaleOrder()
    {
        $transactions = $this->objectManager->create(\Signifyd\Connect\Model\Api\Transactions::class);

        $order = $this->placeQuote($this->getQuote('guest_quote'));
        $transactionsData = $transactions($order);

        //validate required fields
        $this->assertTrue(isset($transactionsData[0]['transactionId']));
        $this->assertTrue(isset($transactionsData[0]['gatewayStatusCode']));
        $this->assertTrue(isset($transactionsData[0]['paymentMethod']));
        $this->assertTrue(isset($transactionsData[0]['amount']));
        $this->assertTrue(isset($transactionsData[0]['currency']));
        $this->assertTrue(isset($transactionsData[0]['gateway']));
    }
}
