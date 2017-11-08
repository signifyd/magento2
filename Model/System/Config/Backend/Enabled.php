<?php
/**
 * Copyright 2017 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */
namespace Signifyd\Connect\Model\System\Config\Backend;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\App\ObjectManager;

class Enabled extends \Magento\Framework\App\Config\Value
{
    public function beforeSave()
    {
        $objectManager = ObjectManager::getInstance();
        $moduleManager = $objectManager->get('\Magento\Framework\Module\Manager');

        $isBuiltinEnabled = $moduleManager->isOutputEnabled('Magento_Signifyd') ? true : false;

        if ($isBuiltinEnabled) {
            $this->setValue(0);
            throw new LocalizedException(__('WARNING: Another instance of Signifyd is currently enabled. Please disable the other running instance to avoid conflicts. <a href="https://www.signifyd.com/resources/faq/magento-2/error-enabling-extension/">More Info</a>.'));
        }

        return $this;
    }
}