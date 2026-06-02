<?php

declare(strict_types=1);

namespace Signifyd\Connect\Block\Adminhtml\System\Config\Field;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class PreAuthNotice extends Field
{
    /**
     * Render pre auth notice
     *
     * @param AbstractElement $element
     * @return string
     */
    public function render(AbstractElement $element)
    {
        return sprintf(
            '<tr id="row_%s"><td colspan="5">'
            . '<div class="message message-notice"><span>%s</span></div>'
            . '</td></tr>',
            $element->getHtmlId(),
            $element->getLabel()
        );
    }
}
