<?php

namespace Signifyd\Connect\Model;

/**
 * ORM model declaration for case data
 */
class Casedata extends \Magento\Framework\Model\AbstractModel
{
    /**
    * Constructor
    *
    * @return void
    */
    protected function _construct()
    {
        parent::_construct();
        $this->_init('Signifyd\Connect\Model\Resource\Casedata');
    }
}
