<?php

namespace Signifyd\Connect\Test\Integration\Cases\Logs;

use Magento\Framework\App\Filesystem\DirectoryList;
use Signifyd\Connect\Model\Casedata;
use Signifyd\Connect\Test\Integration\Cases\Cron\CreateTest;

class CreateLog extends CreateTest
{
    public function testCronCreateCase()
    {
        // Bypassing test
    }

    /**
     * @magentoDataFixture configFixture
     */
    public function testCreateLog()
    {
        $this->processReviewCase(true);
        $case = $this->getCase();
        $requestJson = $this->getRequestJson($case);
        $updateCaseFactory = $this->updateCaseFactory->create();
        $case = $updateCaseFactory($case, $requestJson);

        $this->assertLogValidation($case);
    }

    public function assertLogValidation($case)
    {
        $fileName = $this->logsFile->createLogFile($case->getData('order_id'));
        $filePath = 'media/signifyd_logs/' . $fileName;
        $directory = $this->filesystem->getDirectoryRead(DirectoryList::PUB);

        try {
            $readFile = $directory->readFile($filePath);
        } catch (\Exception $e) {
            $readFile = null;
        }

        $this->assertEquals(Casedata::COMPLETED_STATUS, $case->getData('magento_status'));
        $this->assertTrue(isset($readFile));
    }
}