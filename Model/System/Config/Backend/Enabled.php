<?php
/**
 * Copyright 2017 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */
namespace Signifyd\Connect\Model\System\Config\Backend;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

class Enabled extends \Magento\Framework\App\Config\Value
{
    /**
     * @var ManagerInterface
     */
    protected $messageManager;

    /**
     * @var \Magento\Store\Model\StoreRepository
     */
    protected $storeRepository;

    /**
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param ScopeConfigInterface $config
     * @param \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
     * @param \Magento\Store\Model\StoreRepository $storeRepository
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        ScopeConfigInterface $config,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        ManagerInterface $messageManager,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        \Magento\Store\Model\StoreRepository $storeRepository,
        array $data = []
    ) {
        $this->messageManager = $messageManager;
        $this->storeRepository = $storeRepository;

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
        $moduleManager = $objectManager->get(\Magento\Framework\Module\Manager::class);
        $isBuiltinEnabled = $moduleManager->isOutputEnabled('Magento_Signifyd') ? true : false;

        if (!$isBuiltinEnabled) {
            return $this;
        }

        $currentPath = $this->getPath();
        $signifydPath = 'signifyd/general/enabled';
        $builtinPath = 'fraud_protection/signifyd/active';
        $path = $currentPath == $signifydPath ? $builtinPath : $signifydPath;

        /** @var \Magento\Store\Model\Store $store */
        foreach ($this->storeRepository->getList() as $store) {
            $isAnotherEnabled = $this->_config->getValue($path, 'stores', $store->getCode());

            if ($isAnotherEnabled) {
                if ($this->getOldValue() == 0) {
                    $this->setValue(0);
                }

                $message = __('ERROR: Another Signifyd integration is already enabled. ' .
                    'You must disable the active integration before enabling a new one.');
                $this->messageManager->addErrorMessage($message);

                break;
            }
        }

        return $this;
    }
}
