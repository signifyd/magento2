<?php

declare(strict_types=1);

namespace Signifyd\Connect\Test\Integration\Cases;

use Magento\Framework\App\Config\Storage\WriterInterface;

/**
 * @magentoDbIsolation enabled
 * @magentoAppArea frontend
 */
class PassiveModeTest extends \Signifyd\Connect\Test\Integration\Cases\Cron\ReviewTest
{
    public function testCronReviewCase()
    {
        // Bypassing test
    }

    /**
     * @magentoDataFixture configFixture
     */
    public function testPassiveMode()
    {
        /** @var \Magento\Framework\App\Config\Storage\WriterInterface $writerInterface */
        $writerInterface = $this->objectManager->create(WriterInterface::class);
        $writerInterface->save('signifyd/general/enabled', 'passive');

        parent::testCronReviewCase();
        $order = $this->getOrder();
        $histories = $order->getStatusHistories();

        foreach ($histories as $history) {
            $comments[] = $history->getComment();
        }

        $isPassive = in_array(
            'PASSIVE: Signifyd: order action unhold',
            $comments
        );

        $this->assertTrue($isPassive);
    }
}
