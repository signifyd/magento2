<?php

use Magento\TestFramework\Helper\Bootstrap;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\Encryptor;

require __DIR__ . '/../env.php';

$objectManager = Bootstrap::getObjectManager();
/** @var \Magento\Framework\Encryption\Encryptor $encryptor */
$encryptor = $objectManager->create(Encryptor::class);
$login = $encryptor->encrypt($envSettings['payment/authorizenet_directpost/login']);
$transMd5 = $encryptor->encrypt($envSettings['payment/authorizenet_directpost/trans_md5']);
$transKey = $encryptor->encrypt($envSettings['payment/authorizenet_directpost/trans_key']);

$configData = [
    ScopeConfigInterface::SCOPE_TYPE_DEFAULT => [
        '' => [
            'payment/authorizenet_directpost/active' => '1',
            'payment/authorizenet_directpost/login' => $login,
            'payment/authorizenet_directpost/trans_md5' => $transMd5,
            'payment/authorizenet_directpost/trans_key' => $transKey,
            'payment/authorizenet_directpost/useccv' => '1',
            'payment/authorizenet_directpost/specificcountry' => null,
            'payment/authorizenet_directpost/min_order_total' => null,
            'payment/authorizenet_directpost/max_order_total' => null,
            'payment/authorizenet_directpost/sort_order' => null,
            'payment/authorizenet_directpost/debug' => 1,
            'payment/authorizenet_directpost/test' => 0,
            'payment/authorizenet_directpost/cgi_url' => 'https://test.authorize.net/gateway/transact.dll',
            'payment/authorizenet_directpost/cgi_url_td' => 'https://apitest.authorize.net/xml/v1/request.api',
            'payment/authorizenet_directpost/payment_action' => 'authorize',
        ]
    ]
];

require __DIR__ . '/../apply.php';
