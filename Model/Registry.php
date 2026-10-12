<?php

namespace Signifyd\Connect\Model;

use Magento\Framework\Model\AbstractModel;

class Registry extends AbstractModel
{
    /**
     * Registry key holding the chargeback data of the dispute being processed, if any.
     *
     * Payment integrations build it while the dispute notification is being handled, and
     * Signifyd\Connect\Model\Api\Core\Client reports it in place of the Record Return.
     */
    public const CHARGEBACK_DATA_REGISTRY_KEY = 'signifyd_chargeback_data';
}
