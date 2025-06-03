<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Signifyd\Connect\Api;

use Magento\Framework\Api\SearchCriteriaInterface;

interface RerouteRepositoryInterface
{

    /**
     * Save reroute method.
     *
     * @param \Signifyd\Connect\Api\Data\RerouteInterface $reroute
     * @return \Signifyd\Connect\Api\Data\RerouteInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function save(
        \Signifyd\Connect\Api\Data\RerouteInterface $reroute
    );

    /**
     * Retrieve reroute method.
     *
     * @param string $rerouteId
     * @return \Signifyd\Connect\Api\Data\RerouteInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function get($rerouteId);

    /**
     * Retrieve reroute matching the specified criteria.
     *
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return \Signifyd\Connect\Api\Data\RerouteSearchResultsInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getList(
        \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
    );

    /**
     * Delete reroute method.
     *
     * @param \Signifyd\Connect\Api\Data\RerouteInterface $reroute
     * @return bool true on success
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function delete(
        \Signifyd\Connect\Api\Data\RerouteInterface $reroute
    );

    /**
     * Delete reroute by ID method.
     *
     * @param string $rerouteId
     * @return bool true on success
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function deleteById($rerouteId);
}
