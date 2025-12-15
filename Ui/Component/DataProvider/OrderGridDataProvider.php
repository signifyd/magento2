<?php

namespace Signifyd\Connect\Ui\Component\DataProvider;

use Magento\Framework\Api\Search\SearchResultInterface;

class OrderGridDataProvider extends \Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider
{
    /**
     * @inheritdoc
     */
    public function addFilter(\Magento\Framework\Api\Filter $filter)
    {
        $field = $filter->getField();
        
        $signifydFields = [
            'signifyd_score' => 'score',
            'signifyd_guarantee' => 'guarantee',
            'checkpoint_action_reason' => 'checkpoint_action_reason'
        ];

        if (!isset($signifydFields[$field])) {
            return parent::addFilter($filter);
        }

        //TODO: REMOVER
        /** @var \Signifyd\Connect\Model\ResourceModel\Casedata\Collection $signifydCollection */
        $signifydCollection = \Magento\Framework\App\ObjectManager::getInstance()
            ->get('Signifyd\Connect\Model\ResourceModel\Casedata\CollectionFactory')->create();

        $mappedField = $signifydFields[$field];
        $condition = $filter->getConditionType();
        $value = $filter->getValue();

        $signifydCollection->addFieldToFilter($mappedField, [
            $condition => $value
        ]);

        $orderIds = $signifydCollection->getColumnValues('order_id');

        $replacementFilter = $this->filterBuilder
            ->setField('entity_id')
            ->setConditionType('in')
            ->setValue($orderIds)
            ->create();

        return parent::addFilter($replacementFilter);
    }
}
