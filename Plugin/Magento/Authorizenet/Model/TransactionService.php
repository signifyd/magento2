<?php

namespace Signifyd\Connect\Plugin\Magento\Authorizenet\Model;

use Signifyd\Connect\Model\Registry;

class TransactionService
{
    /**
     * @var Registry
     */
    public $registry;

    /**
     * TransactionService constructor.
     *
     * @param Registry $registry
     */
    public function __construct(
        Registry $registry
    ) {
        $this->registry = $registry;
    }

    /**
     * After get transaction details method.
     *
     * @param \Magento\Authorizenet\Model\TransactionService $subject
     * @param mixed $result
     * @return mixed
     */
    public function afterGetTransactionDetails(\Magento\Authorizenet\Model\TransactionService $subject, $result)
    {
        $this->registry->setData('signifyd_payment_data', $result);
        return $result;
    }
}
