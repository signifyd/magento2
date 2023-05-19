<?php

namespace Signifyd\Connect\Plugin\OrderGrid;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\ResourceModel\Order\Grid\Collection;

class SalesOrderGridPlugin
{
    public function aroundGetSelect(
        \Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult $subject,
        \Closure                                                              $proceed
    )
    {
        $select = $proceed();
        $connection = $subject->getResource()->getConnection();

        if ($subject->getMainTable() === $connection->getTableName('sales_order_grid')) {
            $parts = $select->getPart(\Magento\Framework\DB\Select::FROM);
            if (!isset($parts['signifyd_connect_case'])) {
                $select->joinLeft(
                    ['signifyd_connect_case' => $subject->getTable('signifyd_connect_case')],
                    'main_table.entity_id = signifyd_connect_case.order_id',
                    ['signifyd_score' => 'signifyd_connect_case.score', 'signifyd_guarantee' => 'signifyd_connect_case.guarantee', 'checkpoint_action_reason' => 'signifyd_connect_case.checkpoint_action_reason']
                );
            }
        }
        return $select;
    }

    public function beforeAddFieldToFilter(
        \Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult $subject,
                                                                              $field,
                                                                              $condition = null
    )
    {
        if ($field === 'signifyd_guarantee') {
            if (isset($condition['eq']) && $condition['eq'] === 'ACCEPT') {
                return [$field, ['in' => ['ACCEPT', 'APPROVED']]];
            }
            if (isset($condition['eq']) && $condition['eq'] === 'REJECT') {
                return [$field, ['in' => ['REJECT', 'DECLINED']]];
            }
            if (isset($condition['eq']) && $condition['eq'] === 'HOLD') {
                return [$field, ['in' => ['HOLD', 'PENDING']]];
            }
        }
        return [$field, $condition];
    }
}
