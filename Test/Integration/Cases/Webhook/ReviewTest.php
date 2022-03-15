<?php

namespace Signifyd\Connect\Test\Integration\Cases\Webhook;

use Signifyd\Connect\Test\Integration\OrderTestCase;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Signifyd\Connect\Model\Casedata;

class ReviewTest extends OrderTestCase
{
    protected $incrementId = '100000002';

    /**
     * @magentoDataFixture configFixture
     */
    public function testWebhookReviewCase()
    {
        $order = $this->placeQuote($this->getQuote('guest_quote'));

        $case = $this->getCase();
        $case->setCode('991716767');
        $case->setOrderId($order->getId());
        $case->save();

        /** @var \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig */
        $scopeConfig = $this->objectManager->create(ScopeConfigInterface::class);
        $key = $scopeConfig->getValue('signifyd/general/key', ScopeConfigInterface::SCOPE_TYPE_DEFAULT);

        $request = file_get_contents(__DIR__ . '/../../_files/case/webhook/review-payload.json');
        $hash = base64_encode(hash_hmac('sha256', $request, $key, true));

        /** @var \Signifyd\Connect\Controller\Webhooks\Index $webhookIndex */
        $webhookIndex = $this->objectManager->create(\Signifyd\Connect\Controller\Webhooks\Index::class);
        $webhookIndex->processRequest($request, $hash, 'cases/review');

        /** @var \Signifyd\Connect\Model\Casedata $case */
        $case = $this->getCase();

        $this->assertEquals('APPROVED', $case->getData('guarantee'));
        $this->assertEquals('792', $case->getData('score'));
        $this->assertEquals(Casedata::COMPLETED_STATUS, $case->getData('magento_status'));
    }

    public static function configFixture()
    {
        require __DIR__ . '/../../_files/order/guest_quote_with_addresses_product_simple.php';
        require __DIR__ . '/../../_files/case/create_in_review_fixed_date_time.php';
    }
}
