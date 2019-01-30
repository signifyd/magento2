<?php

namespace Signifyd\Connect\Controller\Webhooks;

use Magento\Framework\App\Request\InvalidRequestException;

class IndexCsrfAwareActionInterface extends Action implements \Magento\Framework\App\CsrfAwareActionInterface {
    public function execute()
    {
    }
}