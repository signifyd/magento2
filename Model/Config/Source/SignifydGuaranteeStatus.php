<?php

namespace Signifyd\Connect\Model\Config\Source;

use Magento\Eav\Model\Entity\Attribute\Source\AbstractSource;
use Magento\Framework\Data\OptionSourceInterface;

class SignifydGuaranteeStatus extends AbstractSource implements OptionSourceInterface
{
    /**
     * To option array method.
     *
     * @return array[]
     */
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

    /**
     * Get all options method.
     *
     * @return array[]
     */
    public function getAllOptions()
    {
        return $this->toOptionArray();
    }
}
