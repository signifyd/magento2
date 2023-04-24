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
                ['signifyd_score' => 'score', 'signifyd_guarantee' => 'guarantee','checkpoint_action_reason' => 'checkpoint_action_reason']
            );
        }
        return [$printQuery, $logQuery];
    }
}
