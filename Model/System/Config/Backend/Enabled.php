<?php
/**
 * Copyright 2017 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */
namespace Signifyd\Connect\Model\System\Config\Backend;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\Message\ManagerInterface;

class Enabled extends \Magento\Framework\App\Config\Value
{
    protected $messageManager;

    /**
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param ScopeConfigInterface $config
     * @param \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        ManagerInterface $messageManager,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->messageManager = $messageManager;

        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * @return $this
     */
    public function beforeSave()
    {
        if ($this->getValue() == 0) {
            return $this;
        }

        $objectManager = ObjectManager::getInstance();
        $moduleManager = $objectManager->get('\Magento\Framework\Module\Manager');
        $isBuiltinEnabled = $moduleManager->isOutputEnabled('Magento_Signifyd') ? true : false;

        if (!$isBuiltinEnabled) {
            return $this;
        }

        $path = $this->getPath();
        $signifydPath = 'signifyd/general/enabled';
        $builtinPath = 'fraud_protection/signifyd/active';

        $isAnotherEnabled = $this->_config->getValue($path == $signifydPath ? $builtinPath : $signifydPath, 'store');

        if ($isAnotherEnabled) {
            if ($this->getOldValue() == 0) {
                $this->setValue(0);
            }

            $this->messageManager->addError(__('ERROR: Another Signifyd integration is already enabled. You must disable the active integration before enabling a new one.'));
        }
        return $this;
    }
}