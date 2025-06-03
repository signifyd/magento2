<?php

namespace Signifyd\Connect\Plugin\Magento\Paypal\Model;

use Signifyd\Connect\Model\Registry;

class Payflowlink
{
    /**
     * @var Registry
     */
    public $registry;

    /**
     * Payflowlink constructor.
     *
     * @param Registry $registry
     */
    public function __construct(
        Registry $registry
    ) {
        $this->registry = $registry;
    }

    /**
     * Before process method.
     *
     * @param \Magento\Paypal\Model\Payflowlink $subject
     * @param mixed $responseData
     * @return array
     */
    public function beforeProcess(\Magento\Paypal\Model\Payflowlink $subject, $responseData)
    {
        $this->registry->setData('signifyd_payment_data', $responseData);
        return [$responseData];
    }
}
