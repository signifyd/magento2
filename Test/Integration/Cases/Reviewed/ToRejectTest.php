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

    /**
     * @magentoDataFixture configFixture
     */
    public function testToReject()
    {
        $this->processReviewCase();
        $case = $this->getCase();
        $requestJson = $this->getRequestJson($case, false);
        $updateCaseV2 = $this->updateCaseV2Factory->create();
        $case = $updateCaseV2($this, $requestJson);

        $this->assertEquals(Casedata::COMPLETED_STATUS, $case->getData('magento_status'));
        $this->assertEquals('REJECT', $case->getData('guarantee'));
        $this->assertEquals('ACCEPT', $case->getOrigData('guarantee'));
    }
}
