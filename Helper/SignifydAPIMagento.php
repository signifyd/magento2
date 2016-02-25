<?php

namespace Signifyd\Connect\Helper;

use Signifyd\Core\SignifydAPI;

class SignifydAPIMagento extends SignifydAPI
{
    public function __construct(
        SignifydSettingsMagento $settings
    ) {
        // TODO: We are swallowing this until we determine the best way to message this
        if(is_null($settings->apiKey))
        {
            $settings->apiKey = "";
        }
        parent::__construct($settings);
    }
}
