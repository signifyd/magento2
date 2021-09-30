<?php

namespace Signifyd\Connect\Test\Integration\Cases\Webhook;

use Signifyd\Connect\Test\Integration\OrderTestCase;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Signifyd\Connect\Model\Casedata;

class ReviewedToApproveTest extends OrderTestCase
{
    protected $incrementId = '100000002';

    /**
     * @magentoDataFixture configFixture
     */
    public function testWebhookReviewCase()
    {
        $request = file_get_contents(__DIR__ . '/../../_files/case/webhook/reviewed-approved-payload.json');
        $jsonSerializer = $this->objectManager->create(\Magento\Framework\Serialize\Serializer\Json::class);
        $requestJson = $jsonSerializer->unserialize($request);

        $case = parent::createDeclinedCompleteCase($requestJson['caseId']);
        $entityId = $case->getId();

        /** @var \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig */
        $scopeConfig = $this->objectManager->create(ScopeConfigInterface::class);
        $key = $scopeConfig->getValue('signifyd/general/key', ScopeConfigInterface::SCOPE_TYPE_DEFAULT);

        $hash = base64_encode(hash_hmac('sha256', $request, $key, true));

        /** @var \Signifyd\Connect\Controller\Webhooks\Index $webhookIndex */
        $webhookIndex = $this->objectManager->create(\Signifyd\Connect\Controller\Webhooks\Index::class);
        $webhookIndex->processRequest($request, $hash, 'cases/review');

        /** @var \Magento\Sales\Model\Order $order */
        $order = $this->objectManager->get(\Magento\Sales\Model\Order::class);
        $order->loadByIncrementId($this->incrementId);
        $histories = $order->getStatusHistories();
        $comments = [];

        foreach ($histories as $history) {
            $comments[] = $history->getComment();
        }

        $isReviewed = in_array(
            'Signifyd: case reviewed on Signifyd from declined to approved. Old score: 333, new score: 945',
            $comments
        );

        /** @var \Signifyd\Connect\Model\Casedata $case */
        $case = $this->getCase(['entity_id' => $entityId]);

        $this->assertEquals('APPROVED', $case->getData('guarantee'));
        $this->assertEquals('945', $case->getData('score'));
        $this->assertEquals(Casedata::COMPLETED_STATUS, $case->getData('magento_status'));
        $this->assertTrue($isReviewed);
    }

    public static function configFixture()
    {
        require __DIR__ . '/../../_files/order/guest_quote_with_addresses_product_simple.php';
        require __DIR__ . '/../../_files/case/create_in_review_fixed_date_time.php';
    }
}
