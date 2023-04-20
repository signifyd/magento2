<?php

namespace Signifyd\Connect\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class OrderStates implements OptionSourceInterface
{
    protected $orderConfig;

    public function __construct(\Magento\Sales\Model\Order\Config $orderConfig)
    {
        $this->orderConfig = $orderConfig;
    }

    /**
     * Returns array to be used in multiselect on back-end
     *
     * @return array
     */
    public function toOptionArray()
    {
        // get available order states
        $arr = [];
        foreach ($this->orderConfig->getStates() as $code => $title) {
            if (!empty($code) && !empty($title)) {
                $arr[] = ['value' => $code, 'label' => __($title) . " [$code]"];
            }
        }
        return $arr;
    }
}
