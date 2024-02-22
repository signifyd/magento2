<?php

namespace Signifyd\Connect\Test\Integration\Cases\Update;

use Signifyd\Connect\Model\Casedata;
use Signifyd\Connect\Test\Integration\Cases\Reviewed\ToRejectTest;

class BypassAdditionalUpdatesTest extends ToRejectTest
{
    public function initConfig()
    {
        /** @var \Magento\Framework\App\Config\Storage\WriterInterface $writerInterface */
        $writerInterface = $this->objectManager->create(\Magento\Framework\App\Config\Storage\WriterInterface::class);
        $writerInterface->save('signifyd/advanced/guarantee_negative_action', 'cancel');
        $writerInterface->save('signifyd/advanced/guarantee_positive_action', 'unhold');
        $writerInterface->save('signifyd/advanced/bypass_additional_updates', 1);
    }

    public function assertEqualsUpdate($case)
    {
        $updateOrder = $this->updateOrderFactory->create();
        $case = $updateOrder($case);
        $order = $this->getOrder($case->getData('order_id'));

        $this->assertEquals(Casedata::COMPLETED_STATUS, $case->getData('magento_status'));
        $this->assertEquals('REJECT', $case->getData('guarantee'));
        $this->assertEquals('ACCEPT', $case->getOrigData('guarantee'));
        $this->assertTrue($order->canCancel());
    }
}
