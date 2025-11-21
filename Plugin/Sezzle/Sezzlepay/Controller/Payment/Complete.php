<?php

namespace Signifyd\Connect\Plugin\Sezzle\Sezzlepay\Controller\Payment;

use Sezzle\Sezzlepay\Controller\Payment\Complete as SezzleComplete;
use Signifyd\Connect\Model\Registry;

class Complete
{
    /**
     * @var Registry
     */
    public $registry;

    /**
     * Complete constructor.
     *
     * @param Registry $registry
     */
    public function __construct(
        Registry $registry
    ) {
        $this->registry = $registry;
    }

    public function beforeExecute(SezzleComplete $subject)
    {
        // Using registry to store payment method info from the request for use during pre_auth validation
        $this->registry->setData('paymentMethod', 'sezzlepay');
    }
}
