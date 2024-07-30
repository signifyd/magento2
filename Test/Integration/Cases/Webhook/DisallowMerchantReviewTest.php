<?php

namespace Signifyd\Connect\Test\Integration\Cases\Webhook;

use Signifyd\Connect\Test\Integration\WebhookTestCase;
use Signifyd\Connect\Model\Casedata;

class DisallowMerchantReviewTest extends WebhookTestCase
{
    public $incrementId = '100000002';

    /**
     * @magentoDataFixture configFixture
     */
    public function testDisallowMerchantReview()
    {
        /** @var \Magento\Framework\App\Config\Storage\WriterInterface $writerInterface */
        $writerInterface = $this->objectManager->create(\Magento\Framework\App\Config\Storage\WriterInterface::class);
        $writerInterface->delete('signifyd/advanced/process_merchant_review_webhook');

        $request = file_get_contents(__DIR__ . '/../../_files/case/webhook/merchant-review.json');
        $this->processWebhookRequest($request, '3621099674');

        /** @var \Signifyd\Connect\Model\Casedata $case */
        $case = $this->getCase();

        $this->assertEquals('N/A', $case->getData('guarantee'));
        $this->assertEquals(Casedata::IN_REVIEW_STATUS, $case->getData('magento_status'));
    }
}