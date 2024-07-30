<?php

namespace Signifyd\Connect\Test\Integration\Cases\Webhook;

use Signifyd\Connect\Test\Integration\WebhookTestCase;
use Signifyd\Connect\Model\Casedata;

class V3ReviewTest extends WebhookTestCase
{
    public $incrementId = '100000002';

    /**
     * @magentoDataFixture configFixture
     */
    public function testWebhookReviewCase()
    {
        $request = file_get_contents(__DIR__ . '/../../_files/case/webhook/review-v3-payload.json');
        $this->processWebhookRequest($request, '3621099674');

        /** @var \Signifyd\Connect\Model\Casedata $case */
        $case = $this->getCase();

        $this->assertEquals('ACCEPT', $case->getData('guarantee'));
        $this->assertEquals('883', $case->getData('score'));
        $this->assertEquals(Casedata::COMPLETED_STATUS, $case->getData('magento_status'));
    }
}
