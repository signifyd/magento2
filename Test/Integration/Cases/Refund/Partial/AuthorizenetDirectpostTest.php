<?php

declare(strict_types=1);

namespace Signifyd\Connect\Test\Integration\Cases\Refund\Partial;

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

        $case = $this->getCase();
        $oldCaseData = json_encode($case->getData());

        $this->refundOrder('partial');

        $case = $this->getCase();
        $newCaseData = json_encode($case->getData());

        $this->assertEquals($oldCaseData, $newCaseData);
    }

    public static function configFixture()
    {
        require __DIR__ . '/../../../_files/settings/payment/authorizenet_directpost.php';
        require __DIR__ . '/../../../_files/order/authorizenet_directpost.php';
    }
}
