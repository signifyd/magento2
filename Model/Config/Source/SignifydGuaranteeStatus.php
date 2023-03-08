<?php

namespace Signifyd\Connect\Model\Config\Source;

use Magento\Eav\Model\Entity\Attribute\Source\AbstractSource;
use Magento\Framework\Option\ArrayInterface;

class SignifydGuaranteeStatus extends AbstractSource implements ArrayInterface
{
    public function toOptionArray()
    {
        $guaranteeStatus = [
            ['label' => 'Reject', 'value' => 'REJECT'],
            ['label' => 'Accepted', 'value' => 'ACCEPT'],
            ['label' => 'Canceled', 'value' => 'CANCELED'],
            ['label' => 'Hold', 'value' => 'HOLD']
        ];

        return $guaranteeStatus;
    }

    public function getAllOptions()
    {
        return $this->toOptionArray();
    }
}
