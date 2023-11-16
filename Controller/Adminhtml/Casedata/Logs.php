<?php

namespace Signifyd\Connect\Controller\Adminhtml\Casedata;

use Magento\Framework\Filesystem;
use Magento\Backend\App\Action;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\LogsFile;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\Filesystem\DirectoryList;

class Logs extends Action
{
    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var LogsFile
     */
    protected $logsFile;

    /**
     * @var FileFactory
     */
    protected $fileFactory;

    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * Array of actions which can be processed without secret key validation
     *
     * @var string[]
     */
    protected $_publicActions = ['logs'];

    /**
     * @param Action\Context $context
     * @param Logger $logger
     * @param LogsFile $logsFile
     * @param FileFactory $fileFactory
     * @param Filesystem $filesystem
     */
    public function __construct(
        Action\Context $context,
        Logger $logger,
        LogsFile $logsFile,
        FileFactory $fileFactory,
        Filesystem $filesystem
    ) {
        parent::__construct($context);
        $this->logger = $logger;
        $this->logsFile = $logsFile;
        $this->fileFactory = $fileFactory;
        $this->filesystem = $filesystem;
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\Result\Json|\Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        try {
            /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
            $resultRedirect = $this->resultRedirectFactory->create();

            $orderId = $this->getRequest()->getParam('order_id');

            if (isset($orderId) === false) {
                $this->messageManager->addErrorMessage(__('Failed to retrieve order id.'));
                return $resultRedirect->setPath('*/*/');
            }

            $fileName = $this->logsFile->createLogFile($orderId);

            if (strpos($fileName, '.txt') === false) {
                $this->messageManager->addErrorMessage(__($fileName));
                return $resultRedirect->setPath('*/*/');
            }

            $filePath = 'media/signifyd_logs/' . $fileName;
            $directory = $this->filesystem->getDirectoryRead(DirectoryList::PUB);

            return $this->fileFactory->create($filePath, $directory->readFile($filePath), DirectoryList::PUB);
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            return $resultRedirect->setPath('*/*/');
        }
    }
}