<?php

namespace Signifyd\Connect\Plugin\Magento\Sales\Model\ResourceModel\Order\Grid;

class Collection
{
    public function beforeAddFieldToFilter(
        \Magento\Sales\Model\ResourceModel\Order\Grid\Collection $subject,
        $field,
        $condition = null
    ) {
        if (in_array($field, ['signifyd_score', 'signifyd_guarantee'])) {
            $field = str_replace('signifyd_', '', $field);
        }

        return [$field, $condition];
    }
}
