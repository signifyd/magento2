<?php

namespace Signifyd\Connect\Model;

use Magento\Framework\Stdlib\DateTime\DateTime;

class SignifydFingerprint
{
    const FILE_NAME = 'signifyd_fingerprint.log';

    protected $directoryList;
    protected $fileDriver;

    /**
     * @var DateTime
     */
    protected $dateTime;

    public function __construct(
        \Magento\Framework\App\Filesystem\DirectoryList $directoryList,
        \Magento\Framework\Filesystem\Driver\File $fileDriver,
        DateTime $dateTime
    ) {
        $this->directoryList = $directoryList;
        $this->fileDriver = $fileDriver;
        $this->dateTime = $dateTime;
    }

    /**
     * @param $time
     * @return void
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    public function addFingerPrintLog($data)
    {
        $filePath = $this->directoryList->getPath('log') . '/' . self::FILE_NAME;

        if (!$this->fileDriver->isExists($filePath)) {
            $directory = dirname($filePath);
            $this->fileDriver->createDirectory($directory);
            $this->fileDriver->touch($filePath);
        }
        $this->fileDriver->filePutContents(
            $filePath,
            PHP_EOL . '[' . $this->dateTime->gmtDate() . '] ' . $data,
            FILE_APPEND);
    }
}
