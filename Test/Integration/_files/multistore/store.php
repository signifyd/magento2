<?php

declare(strict_types=1);

$objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();

$store = $objectManager->create(\Magento\Store\Model\Store::class);

