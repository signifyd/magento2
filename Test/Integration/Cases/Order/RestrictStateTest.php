<?php

namespace Signifyd\Connect\Test\Integration\Cases\Order;

use Signifyd\Connect\Model\Casedata;
use Signifyd\Connect\Test\Integration\Cases\Cron\CreateTest;

class RestrictStateTest extends CreateTest
{
    public function testCronCreateCase()
    {
        // Bypassing test
    }

    /**
     * @magentoDataFixture configFixture
     */
    public function testRestrictStateTest()
    {
        /** @var \Magento\Framework\App\Config\Storage\WriterInterface $writerInterface */
        $writerInterface = $this->objectManager->create(\Magento\Framework\App\Config\Storage\WriterInterface::class);
        $writerInterface->save(
            'signifyd/general/restrict_states_create',
            'new, pending_payment, payment_review, processing, complete, closed, canceled, holded'
        );

        $this->placeQuote($this->getQuote('guest_quote'));
        $case = $this->getCase();

        $this->assertTrue($case->getData('magento_status') === 'new');
    }
}
