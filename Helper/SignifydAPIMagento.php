<?php
/**
 * Copyright 2015 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Signifyd\Connect\Helper;

use Signifyd\Core\SignifydAPI;

class SignifydAPIMagento extends SignifydAPI
{
    /**
     * @var SignifydSettingsMagento Local reference to plugin specific derivative
     */
    private $magentoSettings;

    public function __construct(
        SignifydSettingsMagento $settings
    ) {
        if(is_null($settings->apiKey))
        {
            $settings->apiKey = "";
        }
        $this->magentoSettings = $settings;
        parent::__construct($settings);
    }

    public function enabled()
    {
        return $this->magentoSettings->enabled;
    }
}
