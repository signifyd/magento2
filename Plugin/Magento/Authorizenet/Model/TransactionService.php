<?php

namespace Signifyd\Connect\Plugin\Magento\Authorizenet\Model;

use Signifyd\Connect\Model\Registry;

class TransactionService
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
     * @param \Magento\Authorizenet\Model\TransactionService $subject
     * @param $result
     * @return mixed
     */
    public function afterGetTransactionDetails(\Magento\Authorizenet\Model\TransactionService $subject, $result)
    {
        $this->registry->setData('signifyd_payment_data', $result);
        return $result;
    }
}
