<?php
/**
 * Copyright 2015 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */
namespace Signifyd\Connect\Ui\Component\Listing\Columns;

use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * Class CaseLink
 */
class CaseLink extends Column
{
    /**
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * CaseLink constructor.
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param ScopeConfigInterface $scopeConfig
     * @param ObjectManagerInterface $objectManager
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        ScopeConfigInterface $scopeConfig,
        ObjectManagerInterface $objectManager,
        array $components = [],
        array $data = []
    ) {
        $this->objectManager = $objectManager;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * Prepare Data Source
     *
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            $name = $this->getData('name');
            foreach ($dataSource['data']['items'] as &$item) {
                // Scores should be whole numbers
                if (is_numeric($item[$name])) {
                    $item[$name] = intval($item[$name]);
                } else {
                    /** @var Signifyd\Connect\Model\Casedata $case */
                    $case = $this->objectManager->create('Signifyd\Connect\Model\Casedata')->load($item['increment_id']);
                    $entries = $case->getEntriesText();

                    if (!empty($entries)) {
                        @$entries = unserialize($entries);
                        if (is_array($entries) && isset($entries['testInvestigation']) && $entries['testInvestigation'] == true) {
                            $item[$name] = "TEST: {$item[$name]}";
                        }
                    }
                }

                // The data we display in the grid should link to the case on the Signifyd site
                if (isset($item['signifyd_code']) && $item['signifyd_code'] != '') {
                    $url = "https://www.signifyd.com/cases/" . $item['signifyd_code'];
                    $item[$name] = "<a href=\"$url\" target=\"_blank\">$item[$name]</a>";
                }
            }
        }
        return $dataSource;
    }
}
