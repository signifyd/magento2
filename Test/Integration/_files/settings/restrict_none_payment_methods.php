<?php

use Magento\Framework\App\Config\ScopeConfigInterface;

$configData = [
    ScopeConfigInterface::SCOPE_TYPE_DEFAULT => [
        '' => [
            'signifyd/general/restrict_payment_methods' => '',
        ]
    ]
];

require __DIR__ . '/apply.php';
