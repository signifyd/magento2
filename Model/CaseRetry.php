<?php
/**
 * Copyright 2015 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Signifyd\Connect\Model;

use Magento\Framework\Model\AbstractModel;

/**
 * ORM model declaration for case retry
 */
class CaseRetry extends AbstractModel
{
    /* The status when a case is created */
    const WAITING_SUBMISSION_STATUS     = "waiting_submission";

    /* The status for a case when the first response from Signifyd is received */
    const IN_REVIEW_STATUS              = "in_review";

    /* The status for a case when the case is processing the response */
    const PROCESSING_RESPONSE_STATUS    = "processing_response";

    /* The status for a case that is completed */
    const COMPLETED_STATUS              = "completed";

    /**
     * Constructor
     *
     * @return void
     */
    protected function _construct()
    {
        parent::_construct();
        $this->_init('Signifyd\Connect\Model\ResourceModel\CaseRetry');
    }

}
