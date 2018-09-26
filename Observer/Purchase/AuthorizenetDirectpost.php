<?php

namespace Signifyd\Connect\Observer\Purchase;

use Signifyd\Connect\Observer\Purchase;

class AuthorizenetDirectpost extends Purchase
{
    /**
     * @param \Magento\Framework\Event\Observer $observer
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        /** @var \Magento\Framework\App\Request\Http $request */
        $request = $observer->getEvent()->getRequest();
        $orderIncrementId = $request->getParam('x_invoice_num');

        if (!empty($orderIncrementId)) {
            /** @var \Magento\Sales\Model\Order $order */
            $order = $this->objectManagerInterface->create('\Magento\Sales\Model\Order');
            $order->loadByIncrementId($orderIncrementId);

            if ($order instanceof \Magento\Sales\Model\Order) {
                $observer->getEvent()->setOrder($order);
            }
        }

        return parent::execute($observer, false);
    }
}