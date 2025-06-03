<?php

namespace Signifyd\Connect\Model;

use Magento\Framework\Model\AbstractModel;

class Fulfillment extends AbstractModel
{
    // Fulfillment created on database and not submitted to Signifyd
    public const WAITING_SUBMISSION_STATUS = "waiting_submission";

    // Fulfillment successfully submited to Signifyd
    public const COMPLETED_STATUS = "completed";

    /**
     * Fulfillment construct.
     *
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _construct()
    {
        $this->_init(\Signifyd\Connect\Model\ResourceModel\Fulfillment::class);
    }
}
