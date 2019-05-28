<?php

declare(strict_types=1);

namespace Signifyd\Connect\Test\Integration\Cases\Create\Payment;

use Signifyd\Connect\Test\Integration\TestCase;
use \Magento\Framework\Event\Manager as EventManager;

/**
 * @magentoDbIsolation enabled
 * @magentoAppArea frontend
 */
class BanktransferTest extends TestCase
{
    /**
     * @magentoDataFixture configFixture
     */
    public function testCreateCaseBanktransfer(): void
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order = $this->objectManager->get(\Magento\Sales\Model\Order::class);
        $order->loadByIncrementId('bank_transfer_order');
        $order->setIncrementId($this->incrementId);
        $order->place();
        $order->save();

        /** @var EventManager $eventManager */
        $eventManager = $this->objectManager->get(EventManager::class);
        $eventManager->dispatch('sales_order_save_commit_after', [
            'order' => $order
        ]);

        /** @var \Signifyd\Connect\Model\Casedata $case */
        $case = $this->objectManager->get('\Signifyd\Connect\Model\Casedata');
        $case->load($this->incrementId);

        $this->assertEquals('banktransfer', $order->getPayment()->getMethod());
        $this->assertEquals($this->incrementId, $case->getOrderIncrement());
        $this->assertNotEmpty($case->getCode());
    }

    public static function configFixture()
    {
        require __DIR__ . '/../../../_files/settings/restrict_none_payment_methods.php';
        require __DIR__ . '/../../../_files/settings/payment/banktransfer.php';
        require __DIR__ . '/../../../_files/order/banktransfer.php';
    }
}