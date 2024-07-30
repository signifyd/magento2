<?php

namespace Signifyd\Connect\Test\Integration\Cases\Webhook;

use Signifyd\Connect\Test\Integration\WebhookTestCase;
use Signifyd\Connect\Model\Casedata;

class AllowMerchantReviewTest extends WebhookTestCase
{
    public $incrementId = '100000002';

    /**
     * @magentoDataFixture configFixture
     */
    public function testAllowMerchantReview()
    {
        /** @var \Magento\Framework\App\Config\Storage\WriterInterface $writerInterface */
        $writerInterface = $this->objectManager->create(\Magento\Framework\App\Config\Storage\WriterInterface::class);
        $writerInterface->save('signifyd/advanced/process_merchant_review_webhook', 1);

        $request = file_get_contents(__DIR__ . '/../../_files/case/webhook/merchant-review.json');
        $this->processWebhookRequest($request, '3621099674');

        /** @var \Signifyd\Connect\Model\Casedata $case */
        $case = $this->getCase();

        $this->assertEquals('ACCEPT', $case->getData('guarantee'));
        $this->assertEquals('883', $case->getData('score'));
        $this->assertEquals(Casedata::COMPLETED_STATUS, $case->getData('magento_status'));
    }
}