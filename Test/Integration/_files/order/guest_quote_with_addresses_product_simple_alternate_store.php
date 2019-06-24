<?php

declare(strict_types=1);

use Magento\TestFramework\Helper\Bootstrap;

$store = Bootstrap::getObjectManager()->create(\Magento\Store\Model\Store::class);
$storeId = $store->load('fixturestore')->getId();
$reservedOrderId = 'guest_quote_alt';

require __DIR__ . '/guest_quote_with_addresses_product.php';
