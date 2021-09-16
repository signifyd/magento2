<?php

namespace Signifyd\Connect\Model\Config\Source;

use Magento\Eav\Model\Entity\Attribute\Source\AbstractSource;
use Magento\Framework\Option\ArrayInterface;

class SignifydEnableOptions extends AbstractSource implements ArrayInterface
{
    public function toOptionArray()
    {
        $guaranteeStatus = [
            ['label' => 'Yes', 'value' => 1],
            ['label' => 'No', 'value' => 0],
            ['label' => 'Passive mode (will not update orders)', 'value' => 'passive']
        ];

        return $guaranteeStatus;
    }

    public function getAllOptions()
    {
        return $this->toOptionArray();
    }
}
