<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

if (isset($productType) == false) {
    $productType = 'simple';
}

if (isset($productQty) == false) {
    $productQty = 2;
}

require __DIR__ . '/guest_quote_with_addresses.php';
require_once __DIR__ . "/../product/{$productType}.php";

$objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();

\Magento\TestFramework\Helper\Bootstrap::getInstance()->loadArea(\Magento\Framework\App\Area::AREA_FRONTEND);

$productRepository = $objectManager->create(\Magento\Catalog\Api\ProductRepositoryInterface::class);
$product = $productRepository->get("{$productType}-product-signifyd");

/** @var \Magento\Quote\Model\Quote $quote */
$quote = $objectManager->create(\Magento\Quote\Model\Quote::class);
$quote->load($reservedOrderId, 'reserved_order_id');
$quote->addProduct($product)->setQty($productQty);
$quote->getShippingAddress()->setCollectShippingRates(1);
$quote->collectTotals();

$quoteRepository = $objectManager->create(\Magento\Quote\Api\CartRepositoryInterface::class);
$quoteRepository->save($quote);

/** @var \Magento\Quote\Model\QuoteIdMask $quoteIdMask */
$quoteIdMask = $objectManager->create(\Magento\Quote\Model\QuoteIdMaskFactory::class)->create();
$quoteIdMask->setQuoteId($quote->getId());
$quoteIdMask->setDataChanges(true);
$quoteIdMask->save();
