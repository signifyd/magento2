<?php

use Magento\Framework\App\Config\ScopeConfigInterface;

require __DIR__ . '/env.php';

$configData = [
    ScopeConfigInterface::SCOPE_TYPE_DEFAULT => [
        '' => [
            'signifyd/general/key' => $envSettings['signifyd/general/key'],
            'signifyd/general/enabled' => '1',
        ]
    ]
];

require __DIR__ . '/apply.php';
