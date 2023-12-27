<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Signifyd\Connect\Model;

use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Signifyd\Connect\Api\Data\LogsInterface;
use Signifyd\Connect\Api\Data\LogsInterfaceFactory;
use Signifyd\Connect\Api\Data\LogsSearchResultsInterfaceFactory;
use Signifyd\Connect\Api\LogsRepositoryInterface;
use Signifyd\Connect\Model\ResourceModel\Logs as ResourceLogs;
use Signifyd\Connect\Model\ResourceModel\Logs\CollectionFactory as LogsCollectionFactory;

class LogsRepository implements LogsRepositoryInterface
{

    /**
     * @var ResourceLogs
     */
    protected $resource;

    /**
     * @var LogsInterfaceFactory
     */
    protected $logsFactory;

    /**
     * @var CollectionProcessorInterface
     */
    protected $collectionProcessor;

    /**
     * @var Logs
     */
    protected $searchResultsFactory;

    /**
     * @var LogsCollectionFactory
     */
    protected $logsCollectionFactory;


    /**
     * @param ResourceLogs $resource
     * @param LogsInterfaceFactory $logsFactory
     * @param LogsCollectionFactory $logsCollectionFactory
     * @param LogsSearchResultsInterfaceFactory $searchResultsFactory
     * @param CollectionProcessorInterface $collectionProcessor
     */
    public function __construct(
        ResourceLogs $resource,
        LogsInterfaceFactory $logsFactory,
        LogsCollectionFactory $logsCollectionFactory,
        LogsSearchResultsInterfaceFactory $searchResultsFactory,
        CollectionProcessorInterface $collectionProcessor
    ) {
        $this->resource = $resource;
        $this->logsFactory = $logsFactory;
        $this->logsCollectionFactory = $logsCollectionFactory;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->collectionProcessor = $collectionProcessor;
    }

    /**
     * @inheritDoc
     */
    public function save(LogsInterface $logs)
    {
        try {
            $this->resource->save($logs);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(__(
                'Could not save the logs: %1',
                $exception->getMessage()
            ));
        }
        return $logs;
    }

    /**
     * @inheritDoc
     */
    public function get($logsId)
    {
        $logs = $this->logsFactory->create();
        $this->resource->load($logs, $logsId);
        if (!$logs->getId()) {
            throw new NoSuchEntityException(__('logs with id "%1" does not exist.', $logsId));
        }
        return $logs;
    }

    /**
     * @inheritDoc
     */
    public function getList(
        \Magento\Framework\Api\SearchCriteriaInterface $criteria
    ) {
        $collection = $this->logsCollectionFactory->create();

        $this->collectionProcessor->process($criteria, $collection);

        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($criteria);

        $items = [];
        foreach ($collection as $model) {
            $items[] = $model;
        }

        $searchResults->setItems($items);
        $searchResults->setTotalCount($collection->getSize());
        return $searchResults;
    }

    /**
     * @inheritDoc
     */
    public function delete(LogsInterface $logs)
    {
        try {
            $logsModel = $this->logsFactory->create();
            $this->resource->load($logsModel, $logs->getLogsId());
            $this->resource->delete($logsModel);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(__(
                'Could not delete the logs: %1',
                $exception->getMessage()
            ));
        }
        return true;
    }

    /**
     * @inheritDoc
     */
    public function deleteById($logsId)
    {
        return $this->delete($this->get($logsId));
    }
}
