<?php
/**
 * Copyright 2019 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */
namespace Signifyd\Connect\Controller\Webhooks;

use Magento\Framework\App\Action\Action;

$productMetadataInterface = \Magento\Framework\App\ObjectManager::getInstance()
    ->get('Magento\Framework\App\ProductMetadataInterface');

// Compatibility for versions begore Magento 2.3.0, wich requires to implement CsrfAwareActionInterface
// to accept POST requests
if (version_compare($productMetadataInterface->getVersion(), '2.3.0') >= 0) {
    class IndexPure extends Action implements \Magento\Framework\App\CsrfAwareActionInterface {
        public function execute()
        {
        }

        /**
         * @inheritDoc
         */
        public function createCsrfValidationException(\Magento\Framework\App\RequestInterface $request): \Magento\Framework\App\Request\InvalidRequestException
        {
            return null;
        }

        /**
         * @inheritDoc
         */
        public function validateForCsrf(\Magento\Framework\App\RequestInterface $request): bool
        {
            return true;
        }
    }
} else {
    abstract class IndexPure extends Action {
    }
}