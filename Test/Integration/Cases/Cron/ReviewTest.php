<?php

namespace Signifyd\Connect\Test\Integration\Cases\Cron;

use Signifyd\Connect\Model\Casedata;

class ReviewTest extends CreateTest
{
    public function testCronCreateCase()
    {
        // Bypassing test
    }

    /**
     * @magentoDataFixture configFixture
     */
    public function testCronReviewCase()
    {
        $this->processReviewCase();
        $case = $this->getCase();

        $this->assertEquals(Casedata::COMPLETED_STATUS, $case->getData('magento_status'));
        $this->assertEquals('ACCEPT', $case->getData('guarantee'));
        $this->assertNotEmpty($case->getData('score'));
    }
}
