<?php

namespace Signifyd\Connect\Model\ResourceModel\Order\Grid;

class Collection extends \Magento\Sales\Model\ResourceModel\Order\Grid\Collection
{
    /**
     * @inheritdoc
     */
    protected function _initSelect()
    {
        $return = parent::_initSelect();

        $this->getSelect()->joinLeft(
            ['signifyd_connect_case' => $this->getTable('signifyd_connect_case')],
            'main_table.entity_id = signifyd_connect_case.order_id',
            ['signifyd_score' => 'score', 'signifyd_guarantee' => 'guarantee']
        );

        return $return;
    }
}
