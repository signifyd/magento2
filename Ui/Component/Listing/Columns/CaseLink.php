<?php
/**
 * Copyright 2015 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */
namespace Signifyd\Connect\Ui\Component\Listing\Columns;

use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use Magento\Framework\Serialize\SerializerInterface;
use Signifyd\Connect\Model\CasedataFactory;
use Signifyd\Connect\Model\ResourceModel\Casedata as CasedataResourceModel;

/**
 * Class CaseLink show case link on orger grid
 */
class CaseLink extends Column
{
    /**
     * @var SerializerInterface
     */
    protected $serializer;

    /**
     * @var CasedataFactory
     */
    protected $casedataFactory;

    /**
     * @var CasedataResourceModel
     */
    protected $casedataResourceModel;

    /**
     * CaseLink constructor.
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param SerializerInterface $serializer
     * @param CasedataFactory $casedataFactory
     * @param CasedataResourceModel $casedataResourceModel
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        SerializerInterface $serializer,
        CasedataFactory $casedataFactory,
        CasedataResourceModel $casedataResourceModel,
        array $components = [],
        array $data = []
    ) {
        $this->serializer = $serializer;
        $this->casedataResourceModel = $casedataResourceModel;
        $this->casedataFactory = $casedataFactory;

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
                /** @var \Signifyd\Connect\Model\Casedata $case */
                $case = $this->casedataFactory->create();
                $this->casedataResourceModel->load($case, $item['entity_id'], 'order_id');

                switch ($name) {
                    case "signifyd_score":
                        $item[$name] = $case->getScore();
                        break;

                    case "signifyd_guarantee":
                        $item[$name] = $this->getNameSignifydGuarantee($case, $name);
                        break;

                    case "checkpoint_action_reason":
                        $item[$name] = $case->getCheckpointActionReason();
                        break;
                }

                // The data we display in the grid should link to the case on the Signifyd site
                if (empty($case->getCode()) === false) {
                    $url = "https://www.signifyd.com/cases/" . $case->getCode();
                    $item[$name] = "<a href=\"$url\" target=\"_blank\">$item[$name]</a>";
                }
            }
        }

        return $dataSource;
    }

    /**
     * @param $case
     * @param $name
     * @return string
     */
    public function getNameSignifydGuarantee($case, $name)
    {
        if ($case->getGuarantee() == "ACCEPT") {
            $labelGuarantee = 'APPROVED';
        } elseif ($case->getGuarantee() == "REJECT") {
            $labelGuarantee = 'DECLINED';
        } else {
            $labelGuarantee = $case->getGuarantee();
        }

        $item[$name] = $labelGuarantee;
        $entries = $case->getEntriesText();

        if (!empty($entries)) {
            try {
                $entries = $this->serializer->unserialize($entries);
            } catch (\InvalidArgumentException $e) {
                $entries = [];
            }

            if (is_array($entries) &&
                isset($entries['testInvestigation']) &&
                $entries['testInvestigation'] == true
            ) {
                $item[$name] = "TEST: {$item[$name]}";
            }
        }

        return $item[$name];
    }
}
