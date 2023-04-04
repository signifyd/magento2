<?php

namespace Signifyd\Connect\Test\Integration\Cases\Cron;

use Signifyd\Connect\Model\Casedata;

class CancelNegativeActionTest extends CreateTest
{
    public function testCronCreateCase()
    {
        // Bypassing test
    }

    /**
     * @magentoDataFixture configFixture
     */
    public function testCancelNegativeAction()
    {
        /** @var \Magento\Framework\App\Config\Storage\WriterInterface $writerInterface */
        $writerInterface = $this->objectManager->create(\Magento\Framework\App\Config\Storage\WriterInterface::class);
        $writerInterface->save('signifyd/advanced/guarantee_negative_action', 'cancel');

        $this->processReviewCase(true);
        $case = $this->getCase();
        $order = $this->getOrder();

        $this->assertEquals(Casedata::COMPLETED_STATUS, $case->getData('magento_status'));
        $this->assertEquals('REJECT', $case->getData('guarantee'));
        $this->assertTrue($order->isCanceled());
    }
}
