<?php

declare(strict_types=1);

use Magento\Framework\Encryption\Encryptor;

require __DIR__ . '/guest_quote_with_addresses_product_simple.php';

/** @var \Magento\Framework\Encryption\Encryptor $encryptor */
$encryptor = $objectManager->create(Encryptor::class);

/** @var \Magento\Quote\Model\Quote $quote */
$quote = $objectManager->create(\Magento\Quote\Model\Quote::class);
$quote->load('guest_quote', 'reserved_order_id');

$quote->getPayment()
    ->setCcNumberEnc($encryptor->encrypt('4111111111111111'))
    ->setCcExpMonth('09')
    ->setCcExpYear('2025')
    ->setCcCid('123')
    ->setMethod('authorizenet_directpost')
    ->setAnetTransType('AUTH_ONLY')
    ->setBaseAmountAuthorized($quote->getGrandTotal())
    ->setPoNumber('10101200')
;
$quote->getPayment()->save();

$quote->collectTotals();
$quote->save();
