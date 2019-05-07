<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\Framework\App\Config\ScopeConfigInterface;

$configData = [
    ScopeConfigInterface::SCOPE_TYPE_DEFAULT => [
        '' => [
            'signifyd/general/restrict_payment_methods' => '',
        ]
    ]
];

require __DIR__ . '/apply.php';
