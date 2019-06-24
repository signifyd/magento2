<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

use Magento\TestFramework\Helper\Bootstrap;

if (isset($storeId) == false) {
//    $store = Bootstrap::getObjectManager()->create(\Magento\Store\Model\Store::class);
//    $storeId = $store->load('fixturestore')->getId();

    $storeId = 1;
}

if (isset($reservedOrderId) == false) {
    $reservedOrderId = 'guest_quote';
}

$objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();

require __DIR__ . '/address_list.php';

\Magento\TestFramework\Helper\Bootstrap::getInstance()->loadArea(\Magento\Framework\App\Area::AREA_FRONTEND);


$addressData = reset($addresses);

$billingAddress = $objectManager->create(
    \Magento\Quote\Model\Quote\Address::class,
    ['data' => $addressData]
);
$billingAddress->setAddressType('billing');

$shippingAddress = clone $billingAddress;
$shippingAddress->setId(null)->setAddressType('shipping');

/** @var \Magento\Quote\Model\Quote $quote */
$quote = $objectManager->create(\Magento\Quote\Model\Quote::class);
$quote->setCustomerIsGuest(true)
    ->setStoreId($storeId)
    ->setReservedOrderId($reservedOrderId)
    ->setBillingAddress($billingAddress)
    ->setShippingAddress($shippingAddress);
$quote->getPayment()->setMethod('banktransfer');
$quote->getShippingAddress()->setShippingMethod('flatrate_flatrate')->setCollectShippingRates(1);
$quote->collectTotals();

$quoteRepository = $objectManager->create(\Magento\Quote\Api\CartRepositoryInterface::class);
$quoteRepository->save($quote);

/** @var \Magento\Quote\Model\QuoteIdMask $quoteIdMask */
$quoteIdMask = $objectManager->create(\Magento\Quote\Model\QuoteIdMaskFactory::class)->create();
$quoteIdMask->setQuoteId($quote->getId());
$quoteIdMask->setDataChanges(true);
$quoteIdMask->save();
