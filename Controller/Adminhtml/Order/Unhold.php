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
        try {
            $order = $this->orderRepository->get($this->getRequest()->getParam('order_id'));
        } catch (NoSuchEntityException $e) {
            return parent::execute();
        }

        /** @var $case \Signifyd\Connect\Model\Casedata */
        $case = $this->_objectManager->get('Signifyd\Connect\Model\Casedata');
        $case->load($order->getIncrementId());

        if (!$case->isHoldReleased()) {
            $case->setEntries('hold_released', 1);
            $case->save();
        }

        $resultRedirect = parent::execute();

        $order->addStatusHistoryComment('Order released from hold by merchant');
        $order->save();

        return $resultRedirect;
    }
}
