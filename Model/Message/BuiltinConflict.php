<?php
/**
 * Copyright 2017 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */
namespace Signifyd\Connect\Model\Message;

use Magento\Framework\App\ObjectManager;

class BuiltinConflict implements \Magento\Framework\Notification\MessageInterface
{
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $config;

    /**
     * @var \Magento\Store\Model\StoreRepository
     */
    protected $storeRepository;

    /**
     * @param ScopeConfigInterface $config
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Magento\Store\Model\StoreRepository $storeRepository
    ) {
        $this->config = $config;
        $this->storeRepository = $storeRepository;
    }

    /**
     * Check if Magento_Signifyd is enabled
     *
     * @return bool
     */
    public function isDisplayed()
    {
        $objectManager = ObjectManager::getInstance();
        $moduleManager = $objectManager->get(\Magento\Framework\Module\Manager::class);
        $isBuiltinModuleEnabled = $moduleManager->isOutputEnabled('Magento_Signifyd') ? true : false;

        if (!$isBuiltinModuleEnabled) {
            return false;
        }

        $isSignifydEnabled = 0;
        $isBuiltinEnabled = 0;

        // Checking all store configurations, if any it is enable on both, show warning

        /** @var \Magento\Store\Model\Store $store */
        foreach ($this->storeRepository->getList() as $store) {
            $signifydEnabled = $this->config->getValue('signifyd/general/enabled', 'stores', $store->getCode());
            $builtinEnabled = $this->config->getValue('fraud_protection/signifyd/active', 'stores', $store->getCode());

            if ($signifydEnabled) {
                $isSignifydEnabled = 1;
            }

            if ($builtinEnabled) {
                $isBuiltinEnabled = 1;
            }
        }

        if ($isSignifydEnabled && $isBuiltinEnabled) {
            return true;
        }

        return false;
    }
    /**
     * Retrieve unique message identity
     *
     * @return string
     */
    public function getIdentity()
    {
        return sha1('SIGNIFYD_CONNECT_BUILTIN_CONFLICT');
    }
    /**
     * Retrieve message text
     *
     * @return \Magento\Framework\Phrase
     */
    public function getText()
    {
        return __('WARNING: You have multiple Signifyd integrations enabled. ' .
            'To avoid conflicts, please disable one of the enabled integrations.');
    }
    /**
     * Retrieve message severity
     *
     * @return int
     */
    public function getSeverity()
    {
        return self::SEVERITY_MAJOR;
    }
}
