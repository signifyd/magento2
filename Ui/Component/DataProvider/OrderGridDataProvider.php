<?php

namespace Signifyd\Connect\Ui\Component\DataProvider;

use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\ReportingInterface;
use Magento\Framework\Api\Search\SearchCriteriaBuilder;
use Magento\Framework\App\RequestInterface;
use Signifyd\Connect\Model\ResourceModel\Casedata\CollectionFactory as SignifydCollectionFactory;

class OrderGridDataProvider extends \Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider
{
    /**
     * @var SignifydCollectionFactory
     */
    public $signifydCollectionFactory;

    /**
     * @param SignifydCollectionFactory $signifydCollectionFactory
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param ReportingInterface $reporting
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param RequestInterface $request
     * @param FilterBuilder $filterBuilder
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        SignifydCollectionFactory $signifydCollectionFactory,
        $name,
        $primaryFieldName,
        $requestFieldName,
        ReportingInterface $reporting,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        RequestInterface $request,
        FilterBuilder $filterBuilder,
        array $meta = [],
        array $data = []
    ) {
        $this->signifydCollectionFactory = $signifydCollectionFactory;
        parent::__construct(
            $name,
            $primaryFieldName,
            $requestFieldName,
            $reporting,
            $searchCriteriaBuilder,
            $request,
            $filterBuilder,
            $meta,
            $data
        );
    }

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

        /** @var \Signifyd\Connect\Model\ResourceModel\Casedata\Collection $signifydCollection */
        $signifydCollection = $this->signifydCollectionFactory->create();

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
