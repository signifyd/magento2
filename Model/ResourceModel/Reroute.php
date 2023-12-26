<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Signifyd\Connect\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Reroute extends AbstractDb
{

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init('signifyd_connect_reroute', 'reroute_id');
    }
}
