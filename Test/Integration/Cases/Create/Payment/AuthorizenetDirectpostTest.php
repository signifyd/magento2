<?php

declare(strict_types=1);

namespace Signifyd\Connect\Test\Integration\Cases\Create\Payment;

use Magento\Framework\Event\Manager as EventManager;
use Signifyd\Connect\Test\Integration\OrderTestCase;

/**
 * @magentoDbIsolation enabled
 * @magentoAppArea frontend
 */
class AuthorizenetDirectpostTest extends OrderTestCase
{
    /**
     * @magentoDataFixture configFixture
     */
    public function testCreateCaseAuthorizenetDirectpost()
    {
        $ccTransId = rand(90000000000, 99999999999);
        $xmlFile = __DIR__.'/../../../_files/settings/payment/authorizenet_directpost/transaction_details_response.xml';

        $order = $this->placeQuote($this->getQuote('guest_quote'));
        $order->getPayment()->setCcAvsStatus('Y');
        $order->getPayment()->setCcTransId($ccTransId);
        $order->save();

        $registry = $this->objectManager->get(\Magento\Framework\Registry::class);
        /** @var \Magento\Framework\Simplexml\Element $simplexmlElement */
        $simplexmlElement = simplexml_load_file($xmlFile, '\Magento\Framework\Simplexml\Element');
        $registry->register('signifyd_payment_data', $simplexmlElement);

        /** @var EventManager $eventManager */
        $eventManager = $this->objectManager->get(EventManager::class);
        $eventManager->dispatch('sales_order_save_commit_after', [
            'order' => $order,
            'check_own_events_methods' => false
        ]);

        $case = $this->getCase();

        $this->assertEquals('authorizenet_directpost', $order->getPayment()->getMethod());
        $this->assertEquals($this->incrementId, $case->getOrderIncrement());
        $this->assertNotEmpty($case->getCode());
    }

    public static function configFixture()
    {
        require __DIR__ . '/../../../_files/settings/payment/authorizenet_directpost.php';
        require __DIR__ . '/../../../_files/order/authorizenet_directpost.php';
    }
}
