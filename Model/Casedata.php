<?php
/**
 * Copyright © 2015 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Signifyd\Connect\Model;

use Magento\Framework\Model\AbstractModel;

/**
 * ORM model declaration for case data
 */
class Casedata extends AbstractModel
{
    /**
     * Constructor
     *
     * @return void
     */
    protected function _construct()
    {
        parent::_construct();
        $this->_init('Signifyd\Connect\Model\ResourceModel\Casedata');
    }
}
