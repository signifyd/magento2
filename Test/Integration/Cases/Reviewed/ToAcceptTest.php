<?php

namespace Signifyd\Connect\Test\Integration\Cases\Reviewed;

use Signifyd\Connect\Model\Casedata;
use Signifyd\Connect\Test\Integration\Cases\Cron\CreateTest;

class ToAcceptTest extends CreateTest
{
    public function testCronCreateCase()
    {
        // Bypassing test
    }

    /**
     * @magentoDataFixture configFixture
     */
    public function testToAccept()
    {
        $this->processReviewCase(true);
        $case = $this->getCase();
        $requestJson = $this->getRequestJson($case);
        $updateCaseFactory = $this->updateCaseFactory->create();
        $case = $updateCaseFactory($case, $requestJson);

        $this->assertEquals(Casedata::COMPLETED_STATUS, $case->getData('magento_status'));
        $this->assertEquals('ACCEPT', $case->getData('guarantee'));
        $this->assertEquals('REJECT', $case->getOrigData('guarantee'));
    }
}
