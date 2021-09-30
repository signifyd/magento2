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
    /**
     * @var \Signifyd\Connect\Cron\RetryFulfillmentJob $retryFulfillmentJob
     */
    protected $retryFulfillmentJob;

    protected $paymentMethod = 'banktransfer';

    public function setUp(): void
    {
        parent::setUp();

        $this->retryCaseJob = $this->objectManager->create(\Signifyd\Connect\Cron\RetryCaseJob::class);
        $this->retryFulfillmentJob = $this->objectManager->create(\Signifyd\Connect\Cron\RetryFulfillmentJob::class);
    }

    /**
     * @magentoDataFixture configFixture
     */
    public function testCronCreateCase()
    {
        $case = parent::createCase();
        $entityId = $case->getId();

        require __DIR__ . '/../../_files/settings/restrict_none_payment_methods.php';

        $this->retryCaseJob->execute();

        $case = $this->getCase(['entity_id' => $entityId]);

        $this->assertEquals('PENDING', $case->getData('signifyd_status'));
        $this->assertEquals(Casedata::IN_REVIEW_STATUS, $case->getData('magento_status'));
        $this->assertNotEmpty($case->getData('code'));
    }

    public static function configFixture()
    {
        require __DIR__ . '/../../_files/order/guest_quote_with_addresses_product_simple.php';
    }
}
