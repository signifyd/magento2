<?php

namespace Signifyd\Connect\Observer\Purchase;

use Signifyd\Connect\Observer\Purchase;
use Magento\Framework\Event\Observer;

class PaypalExpress extends Purchase
{
    /**
     * Execute method.
     *
     * @param Observer $observer
     * @param bool $checkOwnEventsMethods
     */
    public function execute(Observer $observer, $checkOwnEventsMethods = true)
    {
        parent::execute($observer, false);
    }
}
