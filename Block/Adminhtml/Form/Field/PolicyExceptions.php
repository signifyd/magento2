<?php
namespace Signifyd\Connect\Block\Adminhtml\Form\Field;

use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;

/**
 * Class Ranges
 */
class PolicyExceptions extends AbstractFieldArray
{
    /**
     * Prepare rendering the new field by adding all the needed columns
     */
    protected function _prepareToRender()
    {
        $this->addColumn('policy', ['label' => __('Policy'), 'class' => 'required-entry']);
        $this->addColumn('payment_method', ['label' => __('Payment Method'), 'class' => 'required-entry']);
        $this->_addAfter = false;
        $this->_addButtonLabel = __('Add');
    }
}