<?php
/**
 * Copyright 2015 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Signifyd\Connect\Model\ResourceModel;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Exception\StateException;

/**
 * ORM model declaration for case data
 */
class Casedata extends AbstractDb
{
    /**
     * @var bool
     */
    public $loadForUpdate = false;

    /**
     * @var ScopeConfigInterface
     */
    public $scopeConfigInterface;

    /**
     * Casedata constructor.
     *
     * @param ScopeConfigInterface $scopeConfigInterface
     * @param \Magento\Framework\Model\ResourceModel\Db\Context $context
     */
    public function __construct(
        ScopeConfigInterface $scopeConfigInterface,
        \Magento\Framework\Model\ResourceModel\Db\Context $context
    ) {
        parent::__construct($context, $connectionName = null);
        $this->scopeConfigInterface = $scopeConfigInterface;
    }

    /**
     * Initialize connection and define main table
     */
    protected function _construct()
    {
        $this->_init('signifyd_connect_case', 'entity_id');
    }

    /**
     * Load case and add lock to start_lock field
     *
     *  Case will be automatically unlocked on save or after $maxLockTime
     *
     * @param \Signifyd\Connect\Model\Casedata $case
     * @param mixed $value
     * @param ?string $field
     * @param int $retry
     * @return Casedata
     * @throws StateException
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     */
    public function loadForUpdate(\Signifyd\Connect\Model\Casedata $case, $value, $field = null, $retry = 0)
    {
        $this->beginTransaction();
        $this->loadForUpdate = true;

        $return = parent::load($case, $value, $field);

        if ($case->isEmpty()) {
            return $return;
        }

        if ($this->isCaseLocked($case)) {
            $this->rollBack();

            if ($retry > 0) {
                sleep(1);
                $this->loadForUpdate($case, $value, $field, $retry-1);
            }

            throw new StateException(__('Case %1 is locked and cannot be loaded for update', $value));
        }

        $case->setLockStart(time());
        parent::save($case);

        $this->loadForUpdate = false;
        $this->getConnection()->commit();

        return $return;
    }

    /**
     * Get load select method.
     *
     * @param string $field
     * @param mixed $value
     * @param \Magento\Framework\Model\AbstractModel $object
     * @return \Magento\Framework\DB\Select|mixed
     */
    protected function _getLoadSelect($field, $value, $object)
    {
        $select = parent::_getLoadSelect($field, $value, $object);

        if ($this->loadForUpdate) {
            $select->forUpdate(true);
        }

        return $select;
    }

    /**
     * Save method.
     *
     * @param \Magento\Framework\Model\AbstractModel $case
     * @return Casedata
     * @throws \Magento\Framework\Exception\AlreadyExistsException
     */
    public function save(\Magento\Framework\Model\AbstractModel $case)
    {
        $case->setLockStart(null);
        return parent::save($case);
    }

    /**
     * Is case locked method.
     *
     * @param \Signifyd\Connect\Model\Casedata $case
     * @return bool
     */
    public function isCaseLocked(\Signifyd\Connect\Model\Casedata $case)
    {
        $lockStart = $case->getLockStart();

        $lockTimeout = $this->scopeConfigInterface->getValue('signifyd/general/lock_timeout');

        if (empty($lockStart) === false) {
            $lockedTime = time() - $lockStart;

            if ($lockedTime <= $lockTimeout) {
                return true;
            }
        }

        return false;
    }
}
