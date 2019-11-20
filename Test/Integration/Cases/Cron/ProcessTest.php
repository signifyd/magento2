<?php

namespace Signifyd\Connect\Test\Integration\Cases\Cron;

use Signifyd\Connect\Model\Casedata;

class ProcessTest extends CreateTest
{
    public function testCronCreateCase()
    {
        // Bypassing test
    }

    /**
     * @magentoDataFixture configFixture
     */
    public function testCronProcessCase()
    {
        parent::testCronCreateCase();

        $this->updateCaseForProcess();

        $case = $this->getCase();
        $this->assertNotEquals(Casedata::COMPLETED_STATUS, $case->getData('magento_status'));

        $this->retryCaseJob->execute();

        $case = $this->getCase();
        $this->assertEquals(Casedata::COMPLETED_STATUS, $case->getData('magento_status'));
    }

    public function updateCaseForProcess($case = null)
    {
        $case = empty($case) ? $this->getCase() : $case;
        $case->setMagentoStatus(Casedata::PROCESSING_RESPONSE_STATUS);
        $case->setUpdated(strftime('%Y-%m-%d %H:%M:%S', time()-60));
        $case->setGuarantee('APPROVED');
        $case->setScore(800);
        $case->save();
    }
}
