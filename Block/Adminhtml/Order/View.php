<?php

namespace Signifyd\Connect\Block\Adminhtml\Order;

class View extends \Magento\Sales\Block\Adminhtml\Order\View
{
    protected $_confirmUnhold = false;

    protected function _construct()
    {
        $order = $this->getOrder();

        if ($this->_isAllowedAction('Magento_Sales::unhold') && $order->canUnhold()) {
            $case = $this->getCase($order);

            if (!$case->isEmpty()) {
                $guarantee = $case->getData('guarantee');

                if (!$case->isHoldReleased() && $guarantee == 'N/A') {
                    $this->_confirmUnhold = true;
                }
            }
        }

        $return = parent::_construct();

        if ($this->_confirmUnhold) {
            $this->buttonList->update('order_unhold', 'class', 'confirm-unhold');
        }

        return $return;
    }

    public function getUnHoldUrl()
    {
        if ($this->_confirmUnhold) {
            return $this->getUrl('signifyd_admin/order/unhold');
        }

        return parent::getUnholdUrl();
    }

    public function getCase(\Magento\Sales\Model\Order $order)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

        /** @var $case \Signifyd\Connect\Model\Casedata */
        $case = $objectManager->get('Signifyd\Connect\Model\Casedata');
        $case->load($order->getIncrementId());
        return $case;
    }
}
