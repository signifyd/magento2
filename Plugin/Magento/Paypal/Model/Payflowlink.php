<?php

namespace Signifyd\Connect\Plugin\Magento\Paypal\Model;

use Signifyd\Connect\Model\Registry;

class Payflowlink
{
    /**
     * @var Registry
     */
    protected $registry;

    /**
     * Payflowlink constructor.
     * @param Registry $registry
     */
    public function __construct(
        Registry $registry
    ) {
        $this->registry = $registry;
    }

    /**
     * @param \Magento\Paypal\Model\Payflowlink $subject
     * @param $responseData
     * @return array
     */
    public function beforeProcess(\Magento\Paypal\Model\Payflowlink $subject, $responseData)
    {
        $this->registry->setData('signifyd_payment_data', $responseData);
        return [$responseData];
    }
}
