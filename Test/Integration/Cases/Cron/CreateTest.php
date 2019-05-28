<?php

namespace Signifyd\Connect\Test\Integration\Cases\Cron;

use Signifyd\Connect\Test\Integration\TestCase;
use Signifyd\Connect\Model\Casedata;

class CreateTest extends TestCase
{
    /**
     * @var \Signifyd\Connect\Cron\RetryCaseJob $retryCaseJob
     */
    protected $retryCaseJob;

    public function setUp()
    {
        parent::setUp();

        $this->retryCaseJob = $this->objectManager->create(\Signifyd\Connect\Cron\RetryCaseJob::class);
    }

    /**
     * @magentoDataFixture configFixture
     */
    public function testCronCreateCase()
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order = $this->objectManager->create(\Magento\Sales\Model\Order::class);
        $order->loadByIncrementId('bank_transfer_order');
        $order->setIncrementId($this->incrementId);
        $order->save();

        /** @var \Signifyd\Connect\Model\Casedata $case */
        $case = $this->objectManager->create(Casedata::class);
        $case->setData([
            'order_increment' => $this->incrementId,
            // Case must be created with 60 seconds before now in order to trigger cron on retries
            'created' => strftime('%Y-%m-%d %H:%M:%S', time()-60),
            'updated' => strftime('%Y-%m-%d %H:%M:%S', time()-60),
            'magento_status' => \Signifyd\Connect\Model\Casedata::WAITING_SUBMISSION_STATUS
        ]);
        $case->save();

        $this->retryCaseJob->execute();

        /** @var \Signifyd\Connect\Model\Casedata $case */
        $case = $this->objectManager->create(Casedata::class);
        $case->load($this->incrementId);

        $this->assertEquals('PENDING', $case->getData('signifyd_status'));
        $this->assertEquals(Casedata::IN_REVIEW_STATUS, $case->getData('magento_status'));
        $this->assertNotEmpty($case->getData('code'));
    }

    public static function configFixture()
    {
        require __DIR__ . '/../../_files/order/banktransfer.php';
    }
}