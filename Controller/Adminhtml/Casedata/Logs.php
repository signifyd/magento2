<?php

namespace Signifyd\Connect\Controller\Adminhtml\Casedata;

use Magento\Framework\Filesystem;
use Magento\Backend\App\Action;
use Signifyd\Connect\Model\LogsFile;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\RequestInterface;

class Logs extends Action
{
    /**
     * @var LogsFile
     */
    public $logsFile;

    /**
     * @var FileFactory
     */
    public $fileFactory;

    /**
     * @var Filesystem
     */
    public $filesystem;

    /**
     * @var RequestInterface
     */
    public $request;

    /**
     * Array of actions which can be processed without secret key validation
     *
     * @var string[]
     */
    public $_publicActions = ['logs'];

    /**
     * @param Action\Context $context
     * @param LogsFile $logsFile
     * @param FileFactory $fileFactory
     * @param Filesystem $filesystem
     * @param RequestInterface $request
     */
    public function __construct(
        Action\Context $context,
        LogsFile $logsFile,
        FileFactory $fileFactory,
        Filesystem $filesystem,
        RequestInterface $request
    ) {
        parent::__construct($context);
        $this->logsFile = $logsFile;
        $this->fileFactory = $fileFactory;
        $this->filesystem = $filesystem;
        $this->request = $request;
    }

    /**
     * Execute method
     *
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\Result\Json|\Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        try {
            /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
            $resultRedirect = $this->resultRedirectFactory->create();

            $orderId = $this->request->getParam('order_id');

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
