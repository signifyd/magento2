<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Signifyd\Connect\Model\ResourceModel\Logs;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'logs_id';

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(
            \Signifyd\Connect\Model\Logs::class,
            \Signifyd\Connect\Model\ResourceModel\Logs::class
        );
    }
}
