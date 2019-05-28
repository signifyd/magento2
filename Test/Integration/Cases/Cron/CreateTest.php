<?php

namespace Test\Integration\Cases\Webhook;

use Signifyd\Connect\Test\Integration\TestCase;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Signifyd\Connect\Model\Casedata;

class CreateTest extends TestCase
{
    /**
     * @magentoDataFixture configFixture
     */
    public function testCronCreateCase()
    {
        /** @var \Signifyd\Connect\Cron\RetryCaseJob $retryCaseJob */
        $retryCaseJob = $this->objectManager->create(\Signifyd\Connect\Cron\RetryCaseJob::class);
        $retryCaseJob->execute();

        /** @var \Signifyd\Connect\Model\Casedata $case */
        $case = $this->objectManager->create(Casedata::class);
        $case->load('100000002');

        $this->assertEquals('PENDING', $case->getData('signifyd_status'));
        $this->assertEquals(Casedata::IN_REVIEW_STATUS, $case->getData('magento_status'));
        $this->assertNotEmpty($case->getData('code'));
    }

    public static function configFixture()
    {
        require __DIR__ . '/../../_files/order/banktransfer.php';
        require __DIR__ . '/../../_files/case/create_waiting_submission_now.php';
    }
}