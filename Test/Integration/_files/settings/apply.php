<?php

use Magento\Config\Model\Config\Factory;
use Magento\Store\Model\ScopeInterface;
use Magento\TestFramework\Helper\Bootstrap;

$objectManager = Bootstrap::getObjectManager();
/** @var Factory $configFactory */
$configFactory = $objectManager->create(Factory::class);

foreach ($configData as $scope => $data) {
    foreach ($data as $scopeCode => $scopeData) {
        foreach ($scopeData as $path => $value) {
            $config = $configFactory->create();
            $config->setScope($scope);

            if ($scope == ScopeInterface::SCOPE_WEBSITES) {
                $config->setWebsite($scopeCode);
            }

            if ($scope == ScopeInterface::SCOPE_STORES) {
                $config->setStore($scopeCode);
            }

            $config->setDataByPath($path, $value);
            $config->save();
        }
    }
}
