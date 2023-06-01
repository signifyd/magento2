<?php

namespace Signifyd\Connect\Model\Api;

use Magento\Framework\App\Config\ScopeConfigInterface;

class Tags
{
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfigInterface;

    /**
     * @param ScopeConfigInterface $scopeConfigInterface
     */
    public function __construct(
        ScopeConfigInterface $scopeConfigInterface
    ) {
        $this->scopeConfigInterface = $scopeConfigInterface;
    }

    /**
     * Construct a new Tags object
     * @return array
     */
    public function __invoke($storeId)
    {
        $enabledConfig = $this->scopeConfigInterface->getValue(
            'signifyd/general/enabled',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORES,
            $storeId
        );

        if ($enabledConfig == 'passive') {
            return ['Passive Mode'];
        }

        return null;
    }
}