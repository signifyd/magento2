<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

$configData = [
    ScopeConfigInterface::SCOPE_TYPE_DEFAULT => [
        '' => [
            'signifyd/advanced/guarantee_positive_action' => 'nothing',
            'signifyd/advanced/guarantee_negative_action' => 'nothing'
        ]
    ],
    ScopeInterface::SCOPE_STORES => [
        'fixturestore' => [
            'signifyd/advanced/guarantee_positive_action' => 'nothing',
            'signifyd/advanced/guarantee_negative_action' => 'cancel'
        ]
    ]
];

require __DIR__ . '/../apply.php';
