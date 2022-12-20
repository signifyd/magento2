<?php

namespace Signifyd\Connect\Test\Integration\Cases\Cron;

use Signifyd\Connect\Model\Casedata;

class CancelOrderTest extends CreateTest
{
    public function testCronCreateCase()
    {
        // Bypassing test
    }

    /**
     * @magentoDataFixture configFixture
     */
    public function testCancelOrder()
    {
        $this->processReviewCase();
        $order = $this->getOrder();
        $order->cancel();
        $case = $this->getCase();

        $this->assertEquals(Casedata::COMPLETED_STATUS, $case->getData('magento_status'));
        $this->assertEquals('CANCELED', $case->getData('guarantee'));
    }
}
