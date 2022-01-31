<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Signifyd\Connect\Controller\Adminhtml\MarkAsFixed;

use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Backend\App\Action\Context;

class Index extends \Magento\Backend\App\Action
{
    /**
     * @var Context
     */
    protected $context;

    /**
     * @var WriterInterface
     */
    protected $configWriter;

    /**
     * UpgradeSchema constructor.
     * @param WriterInterface $configWriter
     * @param Context $context
     */
    public function __construct(
        Context $context,
        WriterInterface $configWriter
    ) {
        parent::__construct($context);
        $this->configWriter = $configWriter;
    }

    public function execute()
    {
        $this->configWriter->delete("signifyd/general/upgrade4.3_inconsistency");

        $this->messageManager->addSuccessMessage(__('Successfully marked as fixed'));
        $this->messageManager->addWarningMessage(__(
            "If the inconsistency message is still visible, it's necessary to clear the config cache"
        ));
        return $this->_redirect($this->_redirect->getRefererUrl());
    }
}
