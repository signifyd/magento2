<?php

namespace Signifyd\Connect\Model;

class SignifydFlags
{
    const FILE_NAME = 'signifyd_flags.json';

    protected $directoryList;
    protected $fileDriver;

    public function __construct(
        \Magento\Framework\App\Filesystem\DirectoryList $directoryList,
        \Magento\Framework\Filesystem\Driver\File $fileDriver
    ) {
        $this->directoryList = $directoryList;
        $this->fileDriver = $fileDriver;
    }

    /**
     * @param $time
     * @return void
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    public function updateWebhookFlag($time = null)
    {
        $filePath = $this->directoryList->getPath('log') . '/' . self::FILE_NAME;
        $data = $this->readFlags();
        if (!$data) {
            $data = [];
        }
        $data['webhook'] = $time ?: date('Y-m-d H:i:s');
        if (!$this->fileDriver->isExists($filePath)) {
            $directory = dirname($filePath);
            $this->fileDriver->createDirectory($directory);
            $this->fileDriver->touch($filePath);
        }
        $this->fileDriver->filePutContents($filePath, json_encode($data));
    }

    /**
     * @param $time
     * @return void
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    public function updateCronFlag($time = null)
    {
        $filePath = $this->directoryList->getPath('log') . '/' . self::FILE_NAME;
        $data = $this->readFlags();
        if (!$data) {
            $data = [];
        }
        $data['cron'] = $time ?: date('Y-m-d H:i:s');
        if (!$this->fileDriver->isExists($filePath)) {
            $directory = dirname($filePath);
            $this->fileDriver->createDirectory($directory);
            $this->fileDriver->touch($filePath);
        }
        $this->fileDriver->filePutContents($filePath, json_encode($data));
    }

    /**
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
