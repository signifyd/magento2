<?php

namespace Signifyd\Connect\Test\Integration\Cases\Order;

use Signifyd\Connect\Model\Casedata;
use Signifyd\Connect\Test\Integration\Cases\Cron\CreateTest;

class RestrictMethodTest extends CreateTest
{
    public function testCronCreateCase()
    {
        // Bypassing test
    }

    /**
     * @magentoDataFixture configFixture
     */
    public function testRestrictMethodTest()
    {
        /** @var \Magento\Framework\App\Config\Storage\WriterInterface $writerInterface */
        $writerInterface = $this->objectManager->create(\Magento\Framework\App\Config\Storage\WriterInterface::class);
        $writerInterface->save(
            'signifyd/general/restrict_payment_methods',
            'banktransfer'
        );

        $this->placeQuote($this->getQuote('guest_quote'));
        $case = $this->getCase();

        $this->assertTrue($case->isEmpty());
    }
}
