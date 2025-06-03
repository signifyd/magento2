<?php

namespace Signifyd\Connect\Model\Config\Source;

use Magento\Eav\Model\Entity\Attribute\Source\AbstractSource;
use Magento\Framework\Data\OptionSourceInterface;

class SignifydEnableOptions extends AbstractSource implements OptionSourceInterface
{
    /**
     * To option array method.
     *
     * @return array
     */
    public function toOptionArray()
    {
        $guaranteeStatus = [
            ['label' => 'Yes', 'value' => 1],
            ['label' => 'No', 'value' => 0],
            ['label' => 'Passive mode (will not update orders)', 'value' => 'passive']
        ];

        return $guaranteeStatus;
    }

    /**
     * Get all options method.
     *
     * @return array
     */
    public function getAllOptions()
    {
        return $this->toOptionArray();
    }
}
