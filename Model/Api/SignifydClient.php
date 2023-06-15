<?php

namespace Signifyd\Connect\Model\Api;

use Magento\Framework\Module\ModuleListInterface;

class SignifydClient
{
    /**
     * @var ModuleListInterface
     */
    protected $moduleList;

    /**
     * @param ModuleListInterface $moduleList
     */
    public function __construct(
        ModuleListInterface $moduleList
    ) {
        $this->moduleList = $moduleList;
    }

    /**
     * Construct a new SignifydClient object
     * @return array
     */
    public function __invoke()
    {
        $version = [];
        $version['application'] = 'Magento 2';
        $version['version'] = (string)($this->moduleList->getOne('Signifyd_Connect')['setup_version']);

        return $version;
    }
}
