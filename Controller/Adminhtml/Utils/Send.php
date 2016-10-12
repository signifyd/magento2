<?php
/**
 * Copyright 2015 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */
namespace Signifyd\Connect\Controller\Adminhtml\Utils;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Sales\Controller\Adminhtml\Order\AbstractMassAction;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Ui\Component\MassAction\Filter;
use Signifyd\Connect\Helper\PurchaseHelper;
use Signifyd\Connect\Helper\SignifydAPIMagento;
use Signifyd\Connect\Helper\LogHelper;

/**
 * Controller action for handling mass sending of Magento orders to Signifyd
 */
class Send extends AbstractMassAction
{
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $_coreConfig;

    /**
     * @var \Signifyd\Connect\Helper\LogHelper
     */
    protected $_logger;

    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $_objectManager;

    /**
     * @var SignifydAPIMagento
     */
    protected $_api;

    /**
     * @var PurchaseHelper
     */
    protected $_helper;

    /**
     * @param Context $context
     * @param ScopeConfigInterface $scopeConfig
     * @param LogHelper $logger
     * @param PurchaseHelper $purchaseHelper
     * @param Filter $filter
     * @param SignifydAPIMagento $api
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        Context $context,
        ScopeConfigInterface $scopeConfig,
        LogHelper $logger,
        PurchaseHelper $purchaseHelper,
        Filter $filter,
        SignifydAPIMagento $api,
        CollectionFactory $collectionFactory
    ) {
        parent::__construct($context, $filter);
        $this->_coreConfig = $scopeConfig;
        $this->_logger = $logger;
        $this->_helper = $purchaseHelper;
        $this->_api = $api;
        $this->collectionFactory = $collectionFactory;
    }

    /**
     * @return bool
     */
    protected function _isAllowed()
    {
        return true;
    }

    public function massAction(AbstractCollection $collection)
    {
        foreach ($collection->getItems() as $order) {
            try {
                // Check if case already exists for this order
                if ($this->_helper->doesCaseExist($order)) {
                    continue;
                }

                $orderData = $this->_helper->processOrderData($order);

                // Add order to database
                $this->_helper->createNewCase($order);

                // Post case to signifyd service
                $this->_helper->postCaseToSignifyd($orderData, $order);
            } catch (\Exception $ex) {
                $this->_logger->error($ex->getMessage());
            }
        }

        $this->messageManager->addSuccessMessage(__('Success.'));

        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setPath($this->getComponentRefererUrl());
        return $resultRedirect;
    }

}
