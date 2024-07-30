<?php

namespace Signifyd\Connect\Test\Integration;

use Magento\Framework\App\Config\ScopeConfigInterface;

class WebhookTestCase extends OrderTestCase
{
    public $incrementId = '100000002';

    /**
     * @magentoDataFixture configFixture
     */
    public function processWebhookRequest($request, $caseId)
    {
        $order = $this->placeQuote($this->getQuote('guest_quote'));

        $case = $this->getCase();
        $case->setCode($caseId);
        $case->setOrderId($order->getId());
        $case->save();

        /** @var \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig */
        $scopeConfig = $this->objectManager->create(ScopeConfigInterface::class);
        $key = $scopeConfig->getValue('signifyd/general/key', ScopeConfigInterface::SCOPE_TYPE_DEFAULT);

        $hash = base64_encode(hash_hmac('sha256', $request, $key, true));

        /** @var \Signifyd\Connect\Controller\Webhooks\Index $webhookIndex */
        $webhookIndex = $this->objectManager->create(\Signifyd\Connect\Controller\Webhooks\Index::class);
        $webhookIndex->processRequest($request, $hash, 'cases/review');
    }

    public static function configFixture()
    {
        require __DIR__ . '/_files/order/guest_quote_with_addresses_product_simple.php';
        require __DIR__ . '/_files/case/create_in_review_fixed_date_time.php';
    }
}