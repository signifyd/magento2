<?php

use Magento\Framework\App\Config\ScopeConfigInterface;

$configData = [
    ScopeConfigInterface::SCOPE_TYPE_DEFAULT => [
        '' => [
            'payment/authorizenet_directpost/active' => '1',
            'payment/authorizenet_directpost/login' => 'TestLogin',
            'payment/authorizenet_directpost/trans_md5' => 'TestHash',
            'payment/authorizenet_directpost/useccv' => '1',
            'payment/authorizenet_directpost/specificcountry' => NULL,
            'payment/authorizenet_directpost/min_order_total' => NULL,
            'payment/authorizenet_directpost/max_order_total' => NULL,
            'payment/authorizenet_directpost/sort_order' => NULL,
            'payment/authorizenet_directpost/debug' => 1,
            'payment/authorizenet_directpost/test' => 0,
            'payment/authorizenet_directpost/cgi_url' => 'https://test.authorize.net/gateway/transact.dll',
            'payment/authorizenet_directpost/cgi_url_td' => 'https://apitest.authorize.net/xml/v1/request.api',
            'payment/authorizenet_directpost/payment_action' => 'authorize',
        ]
    ]
];

require __DIR__ . '/../apply.php';