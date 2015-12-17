<?php

namespace Signifyd\Connect\Helper;

use Signifyd\Core\SignifydAPI;

class SignifydAPIMagento extends SignifydAPI
{
    public function __construct(
        SignifydSettingsMagento $settings
    ) {
        parent::__construct($settings);
    }
}
