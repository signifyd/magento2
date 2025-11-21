<?php

namespace Signifyd\Connect\Plugin\OrderGrid;

use Magento\Framework\DB\Select;
use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;
use Zend_Db_Select_Exception;

class SalesOrderGridPlugin
{
    /**
     * @param SearchResult $subject
     * @param mixed $select
     * @return Select
     * @throws Zend_Db_Select_Exception
     */
    public function afterGetSelect(
        SearchResult $subject,
        mixed $result
    ) {
        $connection = $subject->getResource()->getConnection();

        if ($subject->getMainTable() === $connection->getTableName('sales_order_grid')) {
            $parts = $result->getPart(Select::FROM);
            if (!isset($parts['signifyd_connect_case'])) {
                $result->joinLeft(
                    ['signifyd_connect_case' => $subject->getTable('signifyd_connect_case')],
                    'main_table.entity_id = signifyd_connect_case.order_id',
                    [
                        'signifyd_score' => 'signifyd_connect_case.score',
                        'signifyd_guarantee' => 'signifyd_connect_case.guarantee',
                        'checkpoint_action_reason' => 'signifyd_connect_case.checkpoint_action_reason'
                    ]
                );
            }
        }

        return $result;
    }

    /**
     * Before add field to filter method.
     *
     * @param SearchResult $subject
     * @param mixed $field
     * @param mixed $condition
     * @return array
     */
    public function beforeAddFieldToFilter(
        SearchResult $subject,
                     $field,
                     $condition = null
    ) {
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
