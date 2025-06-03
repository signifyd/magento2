<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Signifyd\Connect\Api;

use Magento\Framework\Api\SearchCriteriaInterface;

interface LogsRepositoryInterface
{

    /**
     * Save logs method.
     *
     * @param \Signifyd\Connect\Api\Data\LogsInterface $logs
     * @return \Signifyd\Connect\Api\Data\LogsInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function save(
        \Signifyd\Connect\Api\Data\LogsInterface $logs
    );

    /**
     * Retrieve logs method.
     *
     * @param string $logsId
     * @return \Signifyd\Connect\Api\Data\LogsInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function get($logsId);

    /**
     * Retrieve logs matching the specified criteria.
     *
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return \Signifyd\Connect\Api\Data\LogsSearchResultsInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getList(
        \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
    );

    /**
     * Delete logs method.
     *
     * @param \Signifyd\Connect\Api\Data\LogsInterface $logs
     * @return bool true on success
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function delete(
        \Signifyd\Connect\Api\Data\LogsInterface $logs
    );

    /**
     * Delete logs by ID method.
     *
     * @param string $logsId
     * @return bool true on success
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function deleteById($logsId);
}
