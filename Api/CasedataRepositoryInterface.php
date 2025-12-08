<?php
/**
 * Copyright 2025 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Signifyd\Connect\Api;

use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\StateException;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Signifyd\Connect\Model\Casedata;

interface CasedataRepositoryInterface
{
    /**
     * Load case by Id
     *
     * @param int $id
     * @return Casedata
     * @throws NoSuchEntityException
     */
    public function getById(int $id): Casedata;

    /**
     * Load case by Code
     *
     * @param string $code
     * @return Casedata
     * @throws NoSuchEntityException
     */
    public function getByCode(string $code): Casedata;

    /**
     * Load case by Order Id
     *
     * @param int $orderId
     * @return Casedata
     * @throws NoSuchEntityException
     */
    public function getByOrderId(int $orderId): Casedata;

    /**
     * Load case by Order Increment Id
     *
     * @param int $orderIncrementId
     * @return Casedata
     * @throws NoSuchEntityException
     */
    public function getByOrderIncrementId(int $orderIncrementId): Casedata;

    /**
     * Load case by Quote Id
     *
     * @param int $quoteId
     * @return Casedata
     * @throws NoSuchEntityException
     */
    public function getByQuoteId(int $quoteId): Casedata;

    /**
     * Save case
     *
     * @param Casedata $case
     * @return Casedata
     * @throws CouldNotSaveException
     */
    public function save(Casedata $case): Casedata;

    /**
     * Delete case
     *
     * @param Casedata $case
     * @return bool
     * @throws CouldNotDeleteException
     */
    public function delete(Casedata $case): bool;

    /**
     * Retrieve loaded case
     *
     * @param int|string $value
     * @param string|null $field
     * @param int $retry
     * @return Casedata
     * @throws AlreadyExistsException
     * @throws StateException
     */
    public function getForUpdate(int|string $value, string $field = null, int $retry = 0): Casedata;

    /**
     * Load case and add lock to start_lock field (case will be automatically unlocked)
     *
     * @param Casedata $case
     * @param int|string $value
     * @param ?string $field
     * @param int $retry
     * @return AbstractDb
     * @throws StateException|AlreadyExistsException
     */
    public function loadForUpdate(Casedata $case, int|string $value, string $field = null, int $retry = 0): AbstractDb;

    /**
     * Check if case is locked
     *
     * @param Casedata $case
     * @return bool
     */
    public function isCaseLocked(Casedata $case): bool;
}