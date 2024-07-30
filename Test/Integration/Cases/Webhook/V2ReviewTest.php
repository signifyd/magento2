<?php

namespace Signifyd\Connect\Test\Integration\Cases\Webhook;

use Signifyd\Connect\Test\Integration\WebhookTestCase;
use Signifyd\Connect\Model\Casedata;

class V2ReviewTest extends WebhookTestCase
{
    public $incrementId = '100000002';

    /**
     * @magentoDataFixture configFixture
     */
    public function testWebhookReviewCase()
    {
        $request = file_get_contents(__DIR__ . '/../../_files/case/webhook/review-payload.json');
        $this->processWebhookRequest($request, '991716767');

        /** @var \Signifyd\Connect\Model\Casedata $case */
        $case = $this->getCase();

        $this->assertEquals('APPROVED', $case->getData('guarantee'));
        $this->assertEquals('792', $case->getData('score'));
        $this->assertEquals(Casedata::COMPLETED_STATUS, $case->getData('magento_status'));
    }
}
