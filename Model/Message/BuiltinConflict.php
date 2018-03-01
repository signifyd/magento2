<?php
/**
 * Copyright 2017 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */
namespace Signifyd\Connect\Model\Message;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\Config\ScopeConfigInterface;

class BuiltinConflict implements \Magento\Framework\Notification\MessageInterface
{
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $config;

    /**
     * @param ScopeConfigInterface $config
     */
    public function __construct(\Magento\Framework\App\Config\ScopeConfigInterface $config)
    {
        $this->config = $config;
    }

    /**
     * Check if Magento_Signifyd is enabled
     *
     * @return bool
     */
    public function isDisplayed()
    {
        $objectManager = ObjectManager::getInstance();
        $moduleManager = $objectManager->get('\Magento\Framework\Module\Manager');
        $isBuiltinModuleEnabled = $moduleManager->isOutputEnabled('Magento_Signifyd') ? true : false;

        if (!$isBuiltinModuleEnabled) {
            return false;
        }

        $isSignifydEnabled = $this->config->getValue('signifyd/general/enabled', 'store');
        $isBuiltinEnabled = $this->config->getValue('fraud_protection/signifyd/active', 'store');

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
        return md5('SIGNIFYD_CONNECT_BUILTIN_CONFLICT');
    }
    /**
     * Retrieve message text
     *
     * @return \Magento\Framework\Phrase
     */
    public function getText()
    {
        return __('WARNING: More than one instance of Signifyd is currently enabled. Please disable one to avoid conflicts.');
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