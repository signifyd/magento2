<?php

namespace Signifyd\Connect\Observer\Purchase;

use Signifyd\Connect\Observer\Purchase;
use Magento\Framework\Event\Observer;

class AuthorizenetDirectpost extends Purchase
{
    /**
     * @param Observer $observer
     * @param bool $checkOwnEventsMethods
     */
    public function execute(Observer $observer, $checkOwnEventsMethods = true)
    {
        /** @var \Magento\Framework\App\Request\Http $request */
        $request = $observer->getEvent()->getRequest();
        $orderIncrementId = $request->getParam('x_invoice_num');

        if (!empty($orderIncrementId)) {
            /** @var \Magento\Sales\Model\Order $order */
            $order = $this->orderFactory->create();
            $this->orderResourceModel->load($order, $orderIncrementId, 'increment_id');

            if ($order instanceof \Magento\Sales\Model\Order) {
                $observer->getEvent()->setOrder($order);
            }
        }

        parent::execute($observer, false);
    }
}
