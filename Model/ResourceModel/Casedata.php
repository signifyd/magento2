<?php
/**
 * Copyright 2015 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Signifyd\Connect\Model\ResourceModel;

use Magento\Framework\DB\Select;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Context;

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
     * Casedata constructor.
     *
     * @param Context $context
     */
    public function __construct(
        Context $context
    ) {
        parent::__construct($context, $connectionName = null);
    }

    /**
     * Initialize connection and define main table
     */
    protected function _construct()
    {
        $this->_init('signifyd_connect_case', 'entity_id');
    }

    /**
     * Get load select method.
     *
     * @param string $field
     * @param mixed $value
     * @param AbstractModel $object
     * @return Select|mixed
     */
    protected function _getLoadSelect($field, $value, $object)
    {
        $select = parent::_getLoadSelect($field, $value, $object);

        if ($this->loadForUpdate) {
            $select->forUpdate(true);
        }

        return $select;
    }
}
