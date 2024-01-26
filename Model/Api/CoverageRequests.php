<?php

namespace Signifyd\Connect\Model\Api;

use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Signifyd\Connect\Helper\ConfigHelper;

class CoverageRequests
{

    /**
     * @var JsonSerializer
     */
    public $jsonSerializer;

    /**
     * @var ConfigHelper
     */
    public $configHelper;

    /**
     * @param JsonSerializer $jsonSerializer
     * @param ConfigHelper $configHelper
     */
    public function __construct(
        JsonSerializer $jsonSerializer,
        ConfigHelper $configHelper
    ) {
        $this->jsonSerializer = $jsonSerializer;
        $this->configHelper = $configHelper;
    }

    /**
     * Construct a new CoverageRequests object
     * @param $paymentMethod
     * @return array|string[]|null
     */
    public function __invoke($paymentMethod = null)
    {
        if ($this->configHelper->isScoreOnly()) {
            return ["NONE"];
        }

        $configDecision = $this->configHelper->getDecisionRequest();

        if (isset($configDecision) === false) {
            return null;
        }

        return $this->getDecisionForMethod($configDecision, $paymentMethod);
    }

    /**
     * @param $decision
     * @param $paymentMethod
     * @return array|null
     */
    protected function getDecisionForMethod($decision, $paymentMethod)
    {
        if (isset($paymentMethod) === false) {
            return null;
        }

        $allowedDecisions = ['FRAUD', 'INR', 'SNAD', 'ALL', 'NONE'];

        try {
            $configDecisions = $this->jsonSerializer->unserialize($decision);
        } catch (\InvalidArgumentException $e) {
            if (in_array($decision, $allowedDecisions) === false) {
                return null;
            }

            return [$decision];
        }

        foreach ($configDecisions as $configDecision => $method) {
            if (in_array($configDecision, $allowedDecisions) === false) {
                continue;
            }

            if (in_array($paymentMethod, $method)) {
                return [$configDecision];
            }
        }

        return null;
    }
}
