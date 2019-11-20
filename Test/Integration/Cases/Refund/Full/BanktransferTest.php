<?php

declare(strict_types=1);

namespace Signifyd\Connect\Test\Integration\Cases\Refund\Full;

use Signifyd\Connect\Test\Integration\Cases\Cron\ReviewTest;

/**
 * @magentoDbIsolation enabled
 * @magentoAppArea frontend
 */
class BanktransferTest extends ReviewTest
{
    public function testCronReviewCase()
    {
        // Bypassing test
    }

    /**
     * @magentoDataFixture configFixture
     */
    public function testRefundOrderBanktransfer()
    {
        parent::testCronReviewCase();

        $this->refundOrder('full');

        $case = $this->getCase();

        $this->assertEquals('CANCELED', $case->getData('guarantee'));
    }
}
