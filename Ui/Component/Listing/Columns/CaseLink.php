<?php
/**
 * Copyright 2015 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */
namespace Signifyd\Connect\Ui\Component\Listing\Columns;

use Braintree\Exception;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use Magento\Framework\Serialize\SerializerInterface;

/**
 * Class CaseLink show case link on orger grid
 */
class CaseLink extends Column
{
    /**
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @var SerializerInterface
     */
    protected $serializer;

    /**
     * CaseLink constructor.
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param ObjectManagerInterface $objectManager
     * @param SerializerInterface $serializer
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        ObjectManagerInterface $objectManager,
        SerializerInterface $serializer,
        array $components = [],
        array $data = []
    ) {
        $this->objectManager = $objectManager;
        $this->serializer = $serializer;
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
                    $item[$name] = (int) $item[$name];
                } else {
                    /** @var \Signifyd\Connect\Model\Casedata $case */
                    $case = $this->objectManager
                        ->create(\Signifyd\Connect\Model\Casedata::class)
                        ->load($item['increment_id']);
                    $entries = $case->getEntriesText();

                    if (!empty($entries)) {
                        try {
                            $entries = $this->serializer->unserialize($entries);
                        } catch (\InvalidArgumentException $e) {
                        }

                        if (is_array($entries) &&
                            isset($entries['testInvestigation']) &&
                            $entries['testInvestigation'] == true
                        ) {
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
