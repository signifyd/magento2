<?php
namespace Signifyd\Connect\Model\System\Config\Source\Options;
/**
 * Option data for positive order actions
 */
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
