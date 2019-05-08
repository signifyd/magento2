<?php

declare(strict_types=1);

namespace Test\Integration\Cases\Create\Payment;

use Magento\TestFramework\Helper\Bootstrap;
use \Magento\Framework\Event\Manager as EventManager;

/**
 * @magentoDbIsolation enabled
 * @magentoAppArea frontend
 */
class AuthorizenetDirectpostTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @inheritdoc
     */
    protected function setUp()
    {
        parent::setUp();
        $this->objectManager = Bootstrap::getObjectManager();
    }

    /**
     * @magentoDataFixture configFixture
     * @magentoDataFixture Magento/Authorizenet/_files/order.php
     */
    public function testCreateCaseAuthorizenetDirectpost(): void
    {
        $orderIncrementId = rand(90000000, 99999999);
        $ccTransId = rand(90000000000, 99999999999);
        $xmlFile = __DIR__ . '/../../../_files/settings/payment/authorizenet_directpost/transaction_details_response.xml';

        /** @var \Magento\Sales\Model\Order $order */
        $order = $this->objectManager->get(\Magento\Sales\Model\Order::class);
        $order->loadByIncrementId('100000002');
        $order->setIncrementId($orderIncrementId);
        $order->getPayment()->setCcAvsStatus('Y');
        $order->getPayment()->setCcTransId($ccTransId);
        $order->place();
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

        /** @var \Signifyd\Connect\Model\Casedata $case */
        $case = $this->objectManager->get('\Signifyd\Connect\Model\Casedata');
        $case->load($orderIncrementId);

        $this->assertEquals($order->getPayment()->getMethod(), 'authorizenet_directpost');
        $this->assertEquals($case->getOrderIncrement(), $orderIncrementId);
        $this->assertNotEmpty($case->getCode());
    }

    public static function configFixture()
    {
        require __DIR__ . '/../../../_files/settings/general.php';
        require __DIR__ . '/../../../_files/settings/payment/authorizenet_directpost.php';
    }
}