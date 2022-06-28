<?php

namespace Signifyd\Connect\Setup\Patch\Data;

use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Signifyd\Connect\Helper\ConfigHelper;

class CoverageRequestUpdate implements DataPatchInterface
{
    /**
     * @var WriterInterface
     */
    protected $configWriter;

    /**
     * @var ConfigHelper
     */
    protected $configHelper;

    /**
     * @param WriterInterface $configWriter
     * @param ConfigHelper $configHelper
     */
    public function __construct(
        WriterInterface $configWriter,
        ConfigHelper $configHelper
    ) {
        $this->configWriter = $configWriter;
        $this->configHelper = $configHelper;
    }

    public function apply()
    {
        $decisionRequest = $this->configHelper->getDecisionRequest();

        switch ($decisionRequest) {
            case 'GUARANTEE':
                $this->configWriter->save('signifyd/general/decision_request', 'FRAUD');
                break;
            case 'SCORE':
            case 'DECISION':
                $this->configWriter->save('signifyd/general/decision_request', 'NONE');
                break;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getAliases()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public static function getDependencies()
    {
        return [];
    }
}
