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
use Signifyd\Connect\Api\Data\RerouteInterface;
use Signifyd\Connect\Api\Data\RerouteInterfaceFactory;
use Signifyd\Connect\Api\Data\RerouteSearchResultsInterfaceFactory;
use Signifyd\Connect\Api\RerouteRepositoryInterface;
use Signifyd\Connect\Model\ResourceModel\Reroute as ResourceReroute;
use Signifyd\Connect\Model\ResourceModel\Reroute\CollectionFactory as RerouteCollectionFactory;

class RerouteRepository implements RerouteRepositoryInterface
{

    /**
     * @var Reroute
     */
    public $searchResultsFactory;

    /**
     * @var RerouteInterfaceFactory
     */
    public $rerouteFactory;

    /**
     * @var CollectionProcessorInterface
     */
    public $collectionProcessor;

    /**
     * @var RerouteCollectionFactory
     */
    public $rerouteCollectionFactory;

    /**
     * @var ResourceReroute
     */
    public $resource;


    /**
     * @param ResourceReroute $resource
     * @param RerouteInterfaceFactory $rerouteFactory
     * @param RerouteCollectionFactory $rerouteCollectionFactory
     * @param RerouteSearchResultsInterfaceFactory $searchResultsFactory
     * @param CollectionProcessorInterface $collectionProcessor
     */
    public function __construct(
        ResourceReroute $resource,
        RerouteInterfaceFactory $rerouteFactory,
        RerouteCollectionFactory $rerouteCollectionFactory,
        RerouteSearchResultsInterfaceFactory $searchResultsFactory,
        CollectionProcessorInterface $collectionProcessor
    ) {
        $this->resource = $resource;
        $this->rerouteFactory = $rerouteFactory;
        $this->rerouteCollectionFactory = $rerouteCollectionFactory;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->collectionProcessor = $collectionProcessor;
    }

    /**
     * @inheritDoc
     */
    public function save(RerouteInterface $reroute)
    {
        try {
            $this->resource->save($reroute);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(__(
                'Could not save the reroute: %1',
                $exception->getMessage()
            ));
        }
        return $reroute;
    }

    /**
     * @inheritDoc
     */
    public function get($rerouteId)
    {
        $reroute = $this->rerouteFactory->create();
        $this->resource->load($reroute, $rerouteId);
        if (!$reroute->getId()) {
            throw new NoSuchEntityException(__('reroute with id "%1" does not exist.', $rerouteId));
        }
        return $reroute;
    }

    /**
     * @inheritDoc
     */
    public function getList(
        \Magento\Framework\Api\SearchCriteriaInterface $criteria
    ) {
        $collection = $this->rerouteCollectionFactory->create();

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
    public function delete(RerouteInterface $reroute)
    {
        try {
            $rerouteModel = $this->rerouteFactory->create();
            $this->resource->load($rerouteModel, $reroute->getRerouteId());
            $this->resource->delete($rerouteModel);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(__(
                'Could not delete the reroute: %1',
                $exception->getMessage()
            ));
        }
        return true;
    }

    /**
     * @inheritDoc
     */
    public function deleteById($rerouteId)
    {
        return $this->delete($this->get($rerouteId));
    }
}
