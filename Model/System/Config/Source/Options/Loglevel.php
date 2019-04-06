<?php

namespace Signifyd\Connect\Model\System\Config\Source\Options;

class Loglevel implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => 1, 'label' => __('Info')],
            ['value' => 0, 'label' => __('None')],
            ['value' => 2, 'label' => __('Debug')]
        ];
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        return [1 => __('Info'), 0 => __('None'), 2 => __('Debug')];
    }
}
