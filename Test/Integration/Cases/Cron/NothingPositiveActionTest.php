<?php

namespace Signifyd\Connect\Test\Integration\Cases\Cron;

use Signifyd\Connect\Model\Casedata;

class NothingPositiveActionTest extends CreateTest
{
    public function testCronCreateCase()
    {
        // Bypassing test
    }

    /**
     * @magentoDataFixture configFixture
     */
    public function testNothingPositiveAction()
    {
        /** @var \Magento\Framework\App\Config\Storage\WriterInterface $writerInterface */
        $writerInterface = $this->objectManager->create(\Magento\Framework\App\Config\Storage\WriterInterface::class);
        $writerInterface->save('signifyd/advanced/guarantee_positive_action', 'nothing');
        $writerInterface->save('signifyd/advanced/guarantee_negative_action', 'cancel');

        $this->processReviewCase();
        $case = $this->getCase();
        $order = $this->getOrder();

        $this->assertEquals(Casedata::COMPLETED_STATUS, $case->getData('magento_status'));
        $this->assertEquals('ACCEPT', $case->getData('guarantee'));
        $this->assertNotEmpty($case->getData('score'));
        $this->assertTrue($order->canUnhold());
        $this->assertFalse($order->hasInvoices());
    }
}
