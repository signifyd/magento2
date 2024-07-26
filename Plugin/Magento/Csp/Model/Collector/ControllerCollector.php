<?php

namespace Signifyd\Connect\Plugin\Magento\Csp\Model\Collector;

use Magento\Csp\Model\Collector\ControllerCollector as MagentoControllerCollector;
use Magento\Csp\Model\Policy\FetchPolicyFactory;

class ControllerCollector
{
    /**
     * @var FetchPolicyFactory
     */
    public $fetchPolicyFactory;

    /**
     * @param FetchPolicyFactory $fetchPolicyFactory
     */
    public function __construct(FetchPolicyFactory $fetchPolicyFactory)
    {
        $this->fetchPolicyFactory = $fetchPolicyFactory;
    }

    public function beforeCollect(MagentoControllerCollector $subject, array $defaultPolicies)
    {
        //plugin necessary to send the fingerprint without the Signifyd script generating a CSP error
        $defaultPolicies[] = $this->fetchPolicyFactory->create(
            [
                'id' => 'script-src',
                'noneAllowed' => false,
                'hostSources' => ['https://h64.online-metrix.net'],
                'schemeSources' => [],
                'selfAllowed' => true,
                'inlineAllowed' => true
            ]
        );

        return [$defaultPolicies];
    }
}
