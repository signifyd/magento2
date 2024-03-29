<?php

namespace Signifyd\Connect\Test\Integration\Cases\Reviewed;

use Signifyd\Connect\Model\Casedata;
use Signifyd\Connect\Test\Integration\Cases\Cron\CreateTest;

class ToRejectTest extends CreateTest
{
    public function testCronCreateCase()
    {
        // Bypassing test
    }

    public function initConfig()
    {
        /** @var \Magento\Framework\App\Config\Storage\WriterInterface $writerInterface */
        $writerInterface = $this->objectManager->create(\Magento\Framework\App\Config\Storage\WriterInterface::class);
        $writerInterface->save('signifyd/advanced/guarantee_negative_action', 'cancel');
        $writerInterface->save('signifyd/advanced/guarantee_positive_action', 'unhold');
    }

    /**
     * @magentoDataFixture configFixture
     */
    public function testToReject()
    {
        $this->initConfig();

        $this->processReviewCase();
        $case = $this->getCase();
        $requestJson = $this->getRequestJson($case, false);
        $updateCaseFactory = $this->updateCaseFactory->create();
        $case = $updateCaseFactory($case, $requestJson);

        $this->assertEqualsUpdate($case);
    }

    public function assertEqualsUpdate($case)
    {
        $updateOrder = $this->updateOrderFactory->create();
        $case = $updateOrder($case);
        $order = $this->getOrder($case->getData('order_id'));

        $this->assertEquals(Casedata::COMPLETED_STATUS, $case->getData('magento_status'));
        $this->assertEquals('REJECT', $case->getData('guarantee'));
        $this->assertEquals('ACCEPT', $case->getOrigData('guarantee'));
        $this->assertFalse($order->canCancel());
    }
}
