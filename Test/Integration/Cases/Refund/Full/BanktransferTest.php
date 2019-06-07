<?php

declare(strict_types=1);

namespace Signifyd\Connect\Test\Integration\Cases\Refund\Partial;

use Signifyd\Connect\Test\Integration\Cases\Cron\ReviewTest;

/**
 * @magentoDbIsolation enabled
 * @magentoAppArea frontend
 */
class BanktransferTest extends ReviewTest
{
    public function testCronReviewCase(): void
    {
        // Bypassing test
    }

    /**
     * @magentoDataFixture configFixture
     */
    public function testRefundOrderBanktransfer(): void
    {
        parent::testCronReviewCase();

        $this->refundOrder('full');

        $case = $this->getCase();

        $this->assertEquals('CANCELED', $case->getData('guarantee'));
    }
}