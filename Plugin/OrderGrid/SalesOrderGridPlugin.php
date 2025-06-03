<?php

namespace Signifyd\Connect\Plugin\OrderGrid;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\ResourceModel\Order\Grid\Collection;
use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;

class SalesOrderGridPlugin
{
    /**
     * Around get select method.
     *
     * @param SearchResult $subject
     * @param \Closure $proceed
     * @return mixed
     */
    public function aroundGetSelect(
        SearchResult $subject,
        \Closure $proceed
    ) {
        $select = $proceed();
        $connection = $subject->getResource()->getConnection();

        if ($subject->getMainTable() === $connection->getTableName('sales_order_grid')) {
            $parts = $select->getPart(\Magento\Framework\DB\Select::FROM);
            if (!isset($parts['signifyd_connect_case'])) {
                $select->joinLeft(
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
        return $select;
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
