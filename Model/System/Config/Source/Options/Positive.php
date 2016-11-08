<?php
/**
 * Copyright 2015 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */
namespace Signifyd\Connect\Model\System\Config\Source\Options;

use Magento\Framework\Option\ArrayInterface;

/**
 * Option data for positive order actions
 */
class Positive implements ArrayInterface
{
    public function toOptionArray()
    {
        return array(
            array(
                'value' => 'hold',
                'label' => 'Leave On Hold',
            ),
            array(
                'value' => 'unhold',
                'label' => 'Unhold Order',
            ),
        );
    }
}
