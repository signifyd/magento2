<?php

declare(strict_types=1);

$objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();

/** @var \Magento\Catalog\Model\Product $product */
$product = $objectManager->create(\Magento\Catalog\Model\Product::class);
$product->setStoreId(0);
$product->setTypeId('simple')
    ->setAttributeSetId($product->getDefaultAttributeSetId())
    ->setName('Simple Product')
    ->setSku('simple-product-signifyd')
    ->setPrice(2)
    ->setTaxClassId(0)
    ->setMetaTitle('meta title')
    ->setMetaKeyword('meta keyword')
    ->setMetaDescription('meta description')
    ->setVisibility(\Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH)
    ->setStatus(\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED)
    ->setStockData(
        [
            'use_config_manage_stock' => 0,
            'manage_stock' => 1,
            'is_in_stock' => 1,
            'qty' => 100,
        ]
    )->save();

$indexerRegistry = $objectManager->get(\Magento\Framework\Indexer\IndexerRegistry::class);

$indexersToReindex = [
    'inventory',
];

foreach ($indexersToReindex as $indexerId) {
    $indexer = $indexerRegistry->get($indexerId);
    if (!$indexer->isInvalid()) {
        $indexer->invalidate();
    }
    $indexer->reindexAll();
}
