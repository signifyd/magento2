<?php
namespace Signifyd\Connect\Model\System\Config\Source\Options;

class Positive implements \Magento\Framework\Option\ArrayInterface
{
    public function toOptionArray()
    {
        return array(
            array(
                'value' => 'nothing',
                'label' => 'Nothing',
            ),
            array(
                'value' => 'unhold',
                'label' => 'Unhold Order',
            ),
        );
    }
}