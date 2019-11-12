<?php

use Magento\Framework\App\Config\ScopeConfigInterface;

$configData = [
    ScopeConfigInterface::SCOPE_TYPE_DEFAULT => [
        '' => [
            'payment/banktransfer/active' => '1'
        ]
    ]
];

require __DIR__ . '/../apply.php';
