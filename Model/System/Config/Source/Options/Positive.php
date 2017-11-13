<?php
/**
 * Copyright 2015 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */
namespace Signifyd\Connect\Model\System\Config\Source\Options;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Option\ArrayInterface;

/**
 * Option data for positive order actions
 */
class Positive implements ArrayInterface
{
    /**
     * @var ScopeConfigInterface
     */
    protected $coreConfig;

    /**
     * Positive constructor.
     * @param ScopeConfigInterface $coreConfig
     */
    public function __construct(ScopeConfigInterface $coreConfig)
    {
        $this->coreConfig = $coreConfig;
    }

    public function toOptionArray()
    {
        $options = array(
            array(
                'value' => 'nothing',
                'label' => 'Do nothing',
            ),
            array(
                'value' => 'unhold',
                'label' => 'Update status to processing',
            ),
        );

        if ($this->coreConfig->getValue('signifyd/advanced/guarantee_positive_action', 'store') == 'hold') {
            $options[] = array(
                'value' => 'hold',
                'label' => 'Leave on hold',
            );
        }

        return $options;
    }
}
