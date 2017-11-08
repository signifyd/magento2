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
     * Check if Magento_Signifyd is enabled
     *
     * @return bool
     */
    public function isDisplayed()
    {
        $objectManager = ObjectManager::getInstance();
        $moduleManager = $objectManager->get('\Magento\Framework\Module\Manager');

        $isBuiltinEnabled = $moduleManager->isOutputEnabled('Magento_Signifyd') ? true : false;

        return $isBuiltinEnabled;
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
        return __('WARNING: Another instance of Signifyd is currently enabled. Please disable the other running instance to avoid conflicts. <a href="https://www.signifyd.com/resources/faq/magento-2/error-enabling-extension/">More Info</a>.');
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
