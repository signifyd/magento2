<?php
/**
 * Copyright 2017 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */
namespace Signifyd\Connect\Model\System\Config\Backend;

use Magento\Framework\Exception\LocalizedException;

class Enabled extends \Magento\Framework\App\Config\Value
{

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
            $this->setValue(0);
            throw new LocalizedException(__('WARNING: Another instance of Signifyd is currently enabled. Please disable the other running instance to avoid conflicts.'));
        }
        return $this;
    }
}