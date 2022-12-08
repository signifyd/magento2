<?php

namespace Signifyd\Connect\Test\Integration\Cases\Cron;

use Signifyd\Connect\Model\Casedata;

class NothingNegativeActionTest extends CreateTest
{
    public function testCronCreateCase()
    {
        // Bypassing test
    }

    /**
     * @magentoDataFixture configFixture
     */
    public function testNothingNegativeAction()
    {
        /** @var \Magento\Framework\App\Config\Storage\WriterInterface $writerInterface */
        $writerInterface = $this->objectManager->create(\Magento\Framework\App\Config\Storage\WriterInterface::class);
        $writerInterface->save('signifyd/advanced/guarantee_positive_action', 'unhold');
        $writerInterface->save('signifyd/advanced/guarantee_negative_action', 'nothing');

        $this->processReviewCase(true);
        $case = $this->getCase();
        $order = $this->getOrder();

        $this->assertEquals(Casedata::COMPLETED_STATUS, $case->getData('magento_status'));
        $this->assertEquals('DECLINED', $case->getData('guarantee'));
        $this->assertTrue($order->canUnhold());
        $this->assertFalse($order->hasInvoices());
    }
}
