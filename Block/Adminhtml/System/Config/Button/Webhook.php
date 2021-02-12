<?php


namespace Signifyd\Connect\Block\Adminhtml\System\Config\Button;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Widget\Block\BlockInterface;

/**
 * Class Inventory
 */
class Webhook extends Field
{
    protected $_template = 'Signifyd_Connect::system/config/button.phtml';

    /**
     * @param AbstractElement $element
     *
     * @return string
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        return $this->_toHtml();
    }

    /**
     * Generate button html
     *
     * @return mixed
     */
    public function getButtonHtml()
    {
        $button = $this->getLayout()->createBlock(
            \Magento\Backend\Block\Widget\Button::class
        )->setData(
            [
                'id' => 'signifyd_general_register_webhook',
                'label' => __('Register Webhook'),
                'onclick' => "location.href='" . $this->getRequiredUrl() . "'"
            ]
        );

        return $button->toHtml();
    }

    /**
     * Get inventory sync url
     *
     * @return string
     */
    public function getRequiredUrl()
    {
        $store = $this->_request->getParam('store');
        $website = $this->_request->getParam('website');

        if (empty($website) == false) {
            $value = ['website' => $website];
        } elseif (empty($store) == false) {
            $value = ['store' => $store];
        } else {
            $value = [];
        }

        return $this->getUrl('signifyd_backend/webhooks/register', $value);
    }
}
