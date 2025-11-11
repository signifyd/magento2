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
use Signifyd\Connect\Api\CasedataRepositoryInterface;
use Signifyd\Connect\Model\CasedataFactory;

/**
 * Class CaseLink show case link on orger grid
 */
class CaseLink extends Column
{
    /**
     * @var SerializerInterface
     */
    public $serializer;

    /**
     * @var CasedataRepositoryInterface
     */
    public $casedataRepository;

    /**
     * @var CasedataFactory
     */
    public $casedataFactory;

    /**
     * CaseLink constructor.
     *
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param SerializerInterface $serializer
     * @param CasedataRepositoryInterface $casedataRepository
     * @param CasedataFactory $casedataFactory
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        SerializerInterface $serializer,
        CasedataRepositoryInterface $casedataRepository,
        CasedataFactory $casedataFactory,
        array $components = [],
        array $data = []
    ) {
        $this->serializer = $serializer;
        $this->casedataRepository = $casedataRepository;
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
                $case = $this->casedataRepository->getByOrderId($item['entity_id']);

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
     * Get name signifyd guarantee method.
     *
     * @param mixed $case
     * @param mixed $name
     * @return string
     */
    public function getNameSignifydGuarantee($case, $name)
    {
        if ($case->getGuarantee() == "APPROVED") {
            $labelGuarantee = 'ACCEPT';
        } elseif ($case->getGuarantee() == "DECLINED") {
            $labelGuarantee = 'REJECT';
        } elseif ($case->getGuarantee() == "PENDING") {
            $labelGuarantee = 'HOLD';
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
