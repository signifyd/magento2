<?php

namespace Signifyd\Connect\Test\Integration\Cases\Cron;

use Signifyd\Connect\Test\Integration\OrderTestCase;
use Signifyd\Connect\Model\Casedata;

class CreateTest extends OrderTestCase
{
    /**
     * @var \Signifyd\Connect\Cron\RetryCaseJob $retryCaseJob
     */
    protected $retryCaseJob;

    protected $paymentMethod = 'banktransfer';

    public function setUp(): void
    {
        parent::setUp();

        $this->retryCaseJob = $this->objectManager->create(\Signifyd\Connect\Cron\RetryCaseJob::class);
    }

    /**
     * @magentoDataFixture configFixture
     */
    public function testCronCreateCase()
    {
        $order = $this->placeQuote($this->getQuote('guest_quote'));
        /** @var \Signifyd\Connect\Model\Casedata $case */
        $case = $this->objectManager->create(Casedata::class);
        $case->setData([
            'order_increment' => $this->incrementId,
            // Case must be created with 60 seconds before now in order to trigger cron on retries
            'created' => date('Y-m-d H:i:s', time()-60),
            'updated' => date('Y-m-d H:i:s', time()-60),
            'order_id' => $order->getId(),
            'magento_status' => \Signifyd\Connect\Model\Casedata::WAITING_SUBMISSION_STATUS
        ]);
        $case->save();

        require __DIR__ . '/../../_files/settings/restrict_none_payment_methods.php';

        $this->retryCaseJob->execute();

        $case = $this->getCase();

        $this->assertEquals('PENDING', $case->getData('signifyd_status'));
        $this->assertEquals(Casedata::IN_REVIEW_STATUS, $case->getData('magento_status'));
        $this->assertNotEmpty($case->getData('code'));
    }

    public static function configFixture()
    {
        require __DIR__ . '/../../_files/order/guest_quote_with_addresses_product_simple.php';
    }
}
