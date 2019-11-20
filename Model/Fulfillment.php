<?php

namespace Signifyd\Connect\Model;

use Magento\Framework\Model\AbstractModel;

class Fulfillment extends AbstractModel
{
    // Fulfillment created on database and not submitted to Signifyd
    const WAITING_SUBMISSION_STATUS = "waiting_submission";

    // Fulfillment successfully submited to Signifyd
    const COMPLETED_STATUS = "completed";

    protected function _construct()
    {
        $this->_init(\Signifyd\Connect\Model\ResourceModel\Fulfillment::class);
    }
}
