<?php

namespace Signifyd\Connect\Model\Config\Source;

use Magento\Eav\Model\Entity\Attribute\Source\AbstractSource;
use Magento\Framework\Option\ArrayInterface;

class SignifydGuaranteeStatus extends AbstractSource implements ArrayInterface
{
    public function toOptionArray()
    {
        $guaranteeStatus = [
            ['label' => 'Declined', 'value' => 'DECLINED'],
            ['label' => 'Approved', 'value' => 'APPROVED'],
            ['label' => 'Canceled', 'value' => 'CANCELED'],
            ['label' => 'Pending', 'value' => 'PENDING']
        ];

        return $guaranteeStatus;
    }

    public function getAllOptions()
    {
        return $this->toOptionArray();
    }
}
