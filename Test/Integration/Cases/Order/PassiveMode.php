<?php

namespace Signifyd\Connect\Test\Integration\Cases\Cron;

use Signifyd\Connect\Model\Casedata;

class PassiveMode extends CreateTest
{
    public function testCronCreateCase()
    {
        // Bypassing test
    }

    /**
     * @magentoDataFixture configFixture
     */
    public function testPassiveModeAction()
    {
        /** @var \Magento\Framework\App\Config\Storage\WriterInterface $writerInterface */
        $writerInterface = $this->objectManager->create(\Magento\Framework\App\Config\Storage\WriterInterface::class);
        $writerInterface->save('signifyd/general/enabled', 'passive');
        $writerInterface->save('signifyd/advanced/guarantee_positive_action', 'capture');

        $this->processReviewCase();
        $case = $this->getCase();
        $order = $this->getOrder();
        $hasPassiveComment = false;

        foreach ($order->getStatusHistoryCollection() as $status) {
            if ($status->getComment() && strpos($status->getComment(), 'PASSIVE') !== false) {
                $hasPassiveComment = true;
            }
        }

        $this->assertEquals(Casedata::COMPLETED_STATUS, $case->getData('magento_status'));
        $this->assertFalse($order->hasInvoices());
        $this->assertTrue($hasPassiveComment);
    }
}
