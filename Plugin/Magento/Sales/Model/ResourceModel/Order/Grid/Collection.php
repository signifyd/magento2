<?php

namespace Signifyd\Connect\Plugin\Magento\Sales\Model\ResourceModel\Order\Grid;

class Collection
{
    /**
     * Before add field to filter method.
     *
     * @param \Magento\Sales\Model\ResourceModel\Order\Grid\Collection $subject
     * @param mixed $field
     * @param mixed $condition
     * @return array
     */
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
