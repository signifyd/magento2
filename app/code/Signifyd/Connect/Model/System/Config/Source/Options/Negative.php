<?php
namespace Signifyd\Connect\Model\System\Config\Source\Options;

class Negative implements \Magento\Framework\Option\ArrayInterface
{
    public function toOptionArray()
    {
        return array(
            array(
                'value' => 'nothing',
                'label' => 'Nothing',
            ),
            array(
                'value' => 'hold',
                'label' => 'Hold Order',
            ),
            array(
                'value' => 'cancel',
                'label' => 'Cancel Order',
            ),
        );
    }
}