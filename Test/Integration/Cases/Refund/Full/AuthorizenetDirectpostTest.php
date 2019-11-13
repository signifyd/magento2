<?php

declare(strict_types=1);

namespace Signifyd\Connect\Test\Integration\Cases\Refund\Full;

use Signifyd\Connect\Test\Integration\Cases\Cron\ReviewTest;

/**
 * @magentoDbIsolation enabled
 * @magentoAppArea frontend
 */
class AuthorizenetDirectpostTest extends ReviewTest
{
    public function testCronReviewCase()
    {
        // Bypassing test
    }

    /**
     * @magentoDataFixture configFixture
     */
    public function testRefundOrderAuthorizenetDirectpost()
    {
        parent::testCronReviewCase();

        $this->refundOrder('full');

        $case = $this->getCase();

        $this->assertEquals('CANCELED', $case->getData('guarantee'));
    }

    public static function configFixture()
    {
        require __DIR__ . '/../../../_files/settings/payment/authorizenet_directpost.php';
        require __DIR__ . '/../../../_files/order/authorizenet_directpost.php';
    }
}
