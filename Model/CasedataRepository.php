<?php
/**
 * Copyright 2025 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Signifyd\Connect\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\StateException;
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
     * @inheritDoc
     */
    public function getById(int $id): Casedata
    {
        $case = $this->casedataFactory->create();
        $this->casedataResource->load($case, $id);

        return $case;
    }

    /**
     * @inheritDoc
     */
    public function getByCode(string $code): Casedata
    {
        $case = $this->casedataFactory->create();
        $this->casedataResource->load($case, $code, 'code');

        return $case;
    }

    /**
     * @inheritDoc
     */
    public function getByOrderId(int $orderId): Casedata
    {
        $case = $this->casedataFactory->create();
        $this->casedataResource->load($case, $orderId, 'order_id');

        return $case;
    }

    /**
     * @inheritDoc
     */
    public function getByOrderIncrementId(int $orderIncrementId): Casedata
    {
        $case = $this->casedataFactory->create();
        $this->casedataResource->load($case, $orderIncrementId, 'order_increment');

        return $case;
    }

    /**
     * @inheritDoc
     */
    public function getByQuoteId(int $quoteId): Casedata
    {
        $case = $this->casedataFactory->create();
        $this->casedataResource->load($case, $quoteId, 'quote_id');

        return $case;
    }

    /**
     * @inheritDoc
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
     * @inheritDoc
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
     * @inheritDoc
     */
    public function getForUpdate(int|string $value, string $field = null, int $retry = 0): Casedata
    {
        $case = $this->casedataFactory->create();
        $this->loadForUpdate($case, $value, $field, $retry);

        return $case;
    }

    /**
     * @inheritDoc
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
     * @inheritDoc
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