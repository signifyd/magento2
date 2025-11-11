<?php
/**
 * Copyright 2025 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Signifyd\Connect\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\StateException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Signifyd\Connect\Api\CasedataRepositoryInterface;
use Signifyd\Connect\Model\CasedataFactory;
use Signifyd\Connect\Model\ResourceModel\Casedata as CasedataResource;

class CasedataRepository implements CasedataRepositoryInterface
{
    /** @var ScopeConfigInterface */
    public ScopeConfigInterface $scopeConfig;

    /** @var CasedataFactory */
    public CasedataFactory $casedataFactory;

    /** @var CasedataResource */
    public CasedataResource $casedataResource;

    /**
     * CasedataRepository constructor.
     *
     * @param ScopeConfigInterface $scopeConfig
     * @param CasedataFactory $casedataFactory
     * @param CasedataResource $casedataResource
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        CasedataFactory $casedataFactory,
        CasedataResource $casedataResource
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->casedataFactory = $casedataFactory;
        $this->casedataResource = $casedataResource;
    }

    /**
     * Load case by Id
     *
     * @param int $id
     * @return Casedata
     * @throws NoSuchEntityException
     */
    public function getById(int $id): Casedata
    {
        $case = $this->casedataFactory->create();
        $this->casedataResource->load($case, $id);

        return $case;
    }

    /**
     * Load case by Code
     *
     * @param string $code
     * @return Casedata
     * @throws NoSuchEntityException
     */
    public function getByCode(string $code): Casedata
    {
        $case = $this->casedataFactory->create();
        $this->casedataResource->load($case, $code, 'code');

        return $case;
    }

    /**
     * Load case by Order Id
     *
     * @param int $orderId
     * @return Casedata
     * @throws NoSuchEntityException
     */
    public function getByOrderId(int $orderId): Casedata
    {
        $case = $this->casedataFactory->create();
        $this->casedataResource->load($case, $orderId, 'order_id');

        return $case;
    }

    /**
     * Load case by Quote Id
     *
     * @param int $quoteId
     * @return Casedata
     * @throws NoSuchEntityException
     */
    public function getByQuoteId(int $quoteId): Casedata
    {
        $case = $this->casedataFactory->create();
        $this->casedataResource->load($case, $quoteId, 'quote_id');

        return $case;
    }

    /**
     * Save case
     *
     * @param Casedata $case
     * @return Casedata
     * @throws CouldNotSaveException
     */
    public function save(Casedata $case): Casedata
    {
        try {
            $case->setLockStart(null);
            $this->casedataResource->save($case);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(
                __('Could not save the case: %1', $e->getMessage())
            );
        }

        return $case;
    }

    /**
     * Delete case
     *
     * @param Casedata $case
     * @return bool
     * @throws CouldNotDeleteException
     */
    public function delete(Casedata $case): bool
    {
        try {
            $this->casedataResource->delete($case);
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(
                __('Could not delete the case: %1', $e->getMessage())
            );
        }

        return true;
    }

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
    public function loadForUpdate(Casedata $case, int|string $value, string $field = null, int $retry = 0): AbstractDb
    {
        $this->casedataResource->beginTransaction();
        $this->casedataResource->loadForUpdate = true;

        $return = $this->casedataResource->load($case, $value, $field);

        if ($case->isEmpty()) {
            return $return;
        }

        if ($this->isCaseLocked($case)) {
            $this->casedataResource->rollBack();

            if ($retry > 0) {
                sleep(1);
                $this->loadForUpdate($case, $value, $field, $retry-1);
            }

            throw new StateException(__('Case %1 is locked and cannot be loaded for update', $value));
        }

        $case->setLockStart(time());
        $this->casedataResource->save($case);

        $this->casedataResource->loadForUpdate = false;
        $this->casedataResource->getConnection()->commit();

        return $return;
    }

    /**
     * Check if case is locked
     *
     * @param Casedata $case
     * @return bool
     */
    public function isCaseLocked(Casedata $case): bool
    {
        $lockStart = $case->getLockStart();

        $lockTimeout = $this->scopeConfig->getValue('signifyd/general/lock_timeout');

        if (empty($lockStart) === false) {
            $lockedTime = time() - $lockStart;

            if ($lockedTime <= $lockTimeout) {
                return true;
            }
        }

        return false;
    }
}