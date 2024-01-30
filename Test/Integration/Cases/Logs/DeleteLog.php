<?php

namespace Signifyd\Connect\Test\Integration\Cases\Logs;

use Magento\Framework\App\Filesystem\DirectoryList;
use Signifyd\Connect\Model\Casedata;

class DeleteLog extends CreateLog
{
    public function assertLogValidation($case)
    {
        $directoryDeleted = false;
        $fileName = $this->logsFile->createLogFile($case->getData('order_id'));
        $filePath = 'media/signifyd_logs/' . $fileName;
        $directory = $this->filesystem->getDirectoryRead(DirectoryList::PUB);

        try {
            $readFile = $directory->readFile($filePath);
        } catch (\Exception $e) {
            $readFile = null;
        }

        if (isset($readFile)) {
            $this->logsFile->prepLogsDir();
            $path = $this->directoryList->getPath('media') . '/signifyd_logs';

            try {
                $this->driverFile->deleteDirectory($path);
                $directoryDeleted = true;
            } catch (\Exception $e) {
            }

            try {
                $readFilePostDelete = $directory->readFile($filePath);
            } catch (\Exception $e) {
                $readFilePostDelete = null;
            }
        }

        $this->assertEquals(Casedata::COMPLETED_STATUS, $case->getData('magento_status'));
        $this->assertTrue($directoryDeleted);
        //must be false after delete directory
        $this->assertFalse(isset($readFilePostDelete));
        //validate if the file is successfully created
        $this->assertTrue(isset($readFile));
    }
}