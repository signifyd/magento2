<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Signifyd\Connect\Model\ResourceModel\Reroute;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{

    /**
     * @inheritDoc
     */
    protected $_idFieldName = 'reroute_id';

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(
            \Signifyd\Connect\Model\Reroute::class,
            \Signifyd\Connect\Model\ResourceModel\Reroute::class
        );
    }
}