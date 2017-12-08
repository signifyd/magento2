<?php

namespace Signifyd\Connect\Controller\Adminhtml\Order;

use Magento\Sales\Model\Order;

class Unhold extends \Magento\Sales\Controller\Adminhtml\Order\Unhold
{
    /**
     * Authorization level of a basic admin session
     *
     * @see _isAllowed()
     */
    const ADMIN_RESOURCE = 'Magento_Sales::unhold';

    /**
     * Unhold order
     *
     * @return \Magento\Backend\Model\View\Result\Redirect
     */
    public function execute()
    {
        $resultRedirect = parent::execute();

        /** @var \Magento\Sales\Model\Order $order */
        $order = $this->_coreRegistry->registry('current_order');
        $order->canUnhold();

        if ($order->getStatus() != Order::STATE_HOLDED) {
            $case = $this->getCase($order);

            if (!$case->isHoldReleased()) {
                $case->setEntries('hold_released', 1);
                $case->save();

                $order->addStatusHistoryComment('Order released from hold by merchant');
                $order->save();
            }
        }

        return $resultRedirect;
    }

    public function getCase(Order $order)
    {
        /** @var $case \Signifyd\Connect\Model\Casedata */
        $case = $this->_objectManager->get('Signifyd\Connect\Model\Casedata');
        $case->load($order->getIncrementId());
        return $case;
    }
}
