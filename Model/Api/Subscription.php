<?php

namespace Signifyd\Connect\Model\Api;

class Subscription
{
    /**
     * Subscription class should be extended/intercepted by plugin to add value to it.
     * If this product is being delivered as part of a subscription, then you can include these fields
     * to include data about the subscription itself. The entire itemQuantity on this
     * product should be purchased via the subscription.
     * If the buyer added extra items of the same product,
     * those should be listed in separate product line item.
     * This method should be extended/intercepted by plugin to add value to it
     *
     * @return null
     */
    public function __invoke()
    {
        return null;
    }
}
