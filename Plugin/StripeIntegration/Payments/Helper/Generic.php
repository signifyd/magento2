<?php

namespace Signifyd\Connect\Plugin\StripeIntegration\Payments\Helper;

use StripeIntegration\Payments\Helper\Generic as ParentGeneric;
use Magento\Framework\App\State;
use Magento\Framework\App\Area;

class Generic
{
    /**
     * @var State
     */
    protected $appState;

    /**
     * Generic constructor.
     * @param State $appState
     */
    public function __construct(State $appState)
    {
        $this->appState = $appState;
    }

    /**
     * @param ParentGeneric $subject
     * @param $return
     * @return bool
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function afterIsAdmin(ParentGeneric $subject, $return)
    {
        if ($return === false) {
            $areaCode = $this->appState->getAreaCode();

            $return = in_array($areaCode, [Area::AREA_FRONTEND, Area::AREA_CRONTAB]);
        }

        return $return;
    }
}
