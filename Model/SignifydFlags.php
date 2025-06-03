<?php

namespace Signifyd\Connect\Model;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Driver\File;

class SignifydFlags
{
    public const FILE_NAME = 'signifyd_flags.json';

    /**
     * @var DirectoryList
     */
    public $directoryList;

    /**
     * @var File
     */
    public $fileDriver;

    /**
     * SignifydFlages construct.
     *
     * @param DirectoryList $directoryList
     * @param File $fileDriver
     */
    public function __construct(
        \Magento\Framework\App\Filesystem\DirectoryList $directoryList,
        \Magento\Framework\Filesystem\Driver\File $fileDriver
    ) {
        $this->directoryList = $directoryList;
        $this->fileDriver = $fileDriver;
    }

    /**
     * Update webhook flag method.
     *
     * @param mixed $time
     * @return void
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    public function updateWebhookFlag($time = null)
    {
        $directory = $this->directoryList->getPath('log');
        $filePath = $directory . '/' . self::FILE_NAME;
        $data = $this->readFlags();
        if (!$data) {
            $data = [];
        }
        $data['webhook'] = $time ?: date('Y-m-d H:i:s');
        if (!$this->fileDriver->isExists($filePath)) {
            $this->fileDriver->createDirectory($directory);
            $this->fileDriver->touch($filePath);
        }
        $this->fileDriver->filePutContents($filePath, json_encode($data));
    }

    /**
     * Update cron flag
     *
     * @param mixed $time
     * @return void
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    public function updateCronFlag($time = null)
    {
        $directory = $this->directoryList->getPath('log');
        $filePath = $directory . '/' . self::FILE_NAME;
        $data = $this->readFlags();
        if (!$data) {
            $data = [];
        }
        $data['cron'] = $time ?: date('Y-m-d H:i:s');
        if (!$this->fileDriver->isExists($filePath)) {
            $this->fileDriver->createDirectory($directory);
            $this->fileDriver->touch($filePath);
        }
        $this->fileDriver->filePutContents($filePath, json_encode($data));
    }

    /**
     * Read flags method.
     *
     * @return mixed|null
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    public function readFlags()
    {
        $filePath = $this->directoryList->getPath('log') . '/' . self::FILE_NAME;
        if ($this->fileDriver->isExists($filePath)) {
            $data = $this->fileDriver->fileGetContents($filePath);
            return json_decode($data, true);
        }
        return null;
    }
}
