<?php

namespace Signifyd\Connect\Model\Api;

class DecisionDelivery
{
    /**
     * Specify SYNC if you require the Response to contain a decision field.
     * If you have registered a webhook endpoint associated with this checkpoint,
     * then a response will be sent to the webhook endpoint even if SYNC is specified.
     * If ASYNC_ONLY is specified, then the decision field in the response will be null,
     * and you will need to register a webhook endpoint to receive Signifyd's final decision via webhook.
     *
     * @return null
     */
    public function __invoke()
    {
        return null;
    }
}
