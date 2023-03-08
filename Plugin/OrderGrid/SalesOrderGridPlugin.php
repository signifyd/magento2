<?php

namespace Signifyd\Connect\Plugin\OrderGrid;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\ResourceModel\Order\Grid\Collection;

class SalesOrderGridPlugin
{
    /**
     * @param Collection $subject
     * @param bool $printQuery
     * @param bool $logQuery
     * @return array
     * @throws LocalizedException
     */
    public function beforeLoad(Collection $subject, bool $printQuery = false, bool $logQuery = false): array
    {
        if (!$subject->isLoaded()) {
            $subject->getSelect()->joinLeft(
                ['signifyd_connect_case' => $subject->getResource()->getTable('signifyd_connect_case')],
                'main_table.entity_id = signifyd_connect_case.order_id',
                ['signifyd_score' => 'score', 'signifyd_guarantee' => 'guarantee']
            );
        }
        return [$printQuery, $logQuery];
    }

    /**
     * @param Collection $collection
     * @param $result
     * @param $field
     * @param $condition
     * @return array|mixed
     */
    public function afterAddFieldToFilter(Collection $collection, $result, $field, $condition = null)
    {
        $guaranteeMapping = [
            'ACCEPT' => ['ACCEPT', 'APPROVED'],
            'HOLD' => ['HOLD','PENDING'],
            'REJECT' => ['REJECT','DECLINED']
        ];

        if ($field === 'guarantee' && isset($condition['eq']) && array_key_exists($condition['eq'], $guaranteeMapping)) {
            $condition = $guaranteeMapping[$condition['eq']];
            $collection->addFieldToFilter($field, ['in' => $condition]);
        }
        return $result;
    }
}
