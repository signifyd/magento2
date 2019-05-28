<?php

namespace Signifyd\Connect\Test\Integration\Cases\Webhook;

use Signifyd\Connect\Test\Integration\TestCase;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Signifyd\Connect\Model\Casedata;

class ReviewTest extends TestCase
{
    /**
     * @magentoDataFixture configFixture
     */
    public function testWebhookReviewCase()
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order = $this->objectManager->create(\Magento\Sales\Model\Order::class);
        $order->loadByIncrementId('bank_transfer_order');
        $order->setIncrementId('100000002');
        $order->save();

        /** @var \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig */
        $scopeConfig = $this->objectManager->create(ScopeConfigInterface::class);
        $key = $scopeConfig->getValue('signifyd/general/key', ScopeConfigInterface::SCOPE_TYPE_DEFAULT);

        $request = file_get_contents(__DIR__ . '/../../_files/case/webhook/review-payload.json');
        $hash = base64_encode(hash_hmac('sha256', $request, $key, true));

        /** @var \Signifyd\Connect\Controller\Webhooks\Index $webhookIndex */
        $webhookIndex = $this->objectManager->create(\Signifyd\Connect\Controller\Webhooks\Index::class);
        $webhookIndex->processRequest($request, $hash, 'cases/review');

        /** @var \Signifyd\Connect\Model\Casedata $case */
        $case = $this->objectManager->create(Casedata::class);
        $case->load('100000002');

        $this->assertEquals('APPROVED', $case->getData('guarantee'));
        $this->assertEquals('792', $case->getData('score'));
        $this->assertEquals(Casedata::COMPLETED_STATUS, $case->getData('magento_status'));
    }

    public static function configFixture()
    {
        require __DIR__ . '/../../_files/order/banktransfer.php';
        require __DIR__ . '/../../_files/case/create_in_review_fixed_date_time.php';
    }
}