<?php
/**
 * Copyright 2015 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */
namespace Signifyd\Connect\Model\Casedata;

use Magento\Sales\Model\OrderFactory;
use Signifyd\Connect\Helper\ConfigHelper;
use Signifyd\Connect\Helper\OrderHelper;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\Casedata;
use Signifyd\Connect\Model\ResourceModel\Order as SignifydOrderResourceModel;

/**
 * Defines link data for the comment field in the config page
 */
class UpdateCaseV2
{
    /**
     * @var ConfigHelper
     */
    protected $configHelper;

    /**
     * @var OrderHelper
     */
    protected $orderHelper;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var OrderFactory
     */
    protected $orderFactory;

    /**
     * @var SignifydOrderResourceModel
     */
    protected $signifydOrderResourceModel;

    /**
     * @param ConfigHelper $configHelper
     * @param OrderHelper $orderHelper
     * @param Logger $logger
     * @param OrderFactory $orderFactory
     * @param SignifydOrderResourceModel $signifydOrderResourceModel
     */
    public function __construct(
        ConfigHelper $configHelper,
        OrderHelper $orderHelper,
        Logger $logger,
        OrderFactory $orderFactory,
        SignifydOrderResourceModel $signifydOrderResourceModel
    ) {
        $this->configHelper = $configHelper;
        $this->orderHelper = $orderHelper;
        $this->logger = $logger;
        $this->orderFactory = $orderFactory;
        $this->signifydOrderResourceModel = $signifydOrderResourceModel;
    }

    /**
     * @param $case
     * @param $response
     * @return mixed
     */
    public function __invoke($case, $response)
    {
        try {
            if (isset($response->score) && $case->getScore() !== $response->score) {
                $case->setScore(floor($response->score));
            }

            $isScoreOnly = $this->configHelper->isScoreOnly();
            $caseScore = $case->getScore();

            if (isset($caseScore) && $isScoreOnly) {
                $case->setMagentoStatus(Casedata::COMPLETED_STATUS);
            }

            if (isset($response->status) && $case->getSignifydStatus() != $response->status) {
                $case->setSignifydStatus($response->status);
            }

            if (isset($response->guaranteeDisposition) && $case->getGuarantee() != $response->guaranteeDisposition) {
                $case->setGuarantee($response->guaranteeDisposition);
            }

            if (isset($response->checkpointAction) && $case->getGuarantee() != $response->checkpointAction) {
                $case->setGuarantee($response->checkpointAction);
            }

            if (isset($response->checkpointActionReason) &&
                $case->getCheckpointActionReason() != $response->checkpointActionReason) {
                $case->setCheckpointActionReason($response->checkpointActionReason);
            }

            if (isset($response->caseId) && empty($response->caseId) == false) {
                $case->setCode($response->caseId);
            }

            if (isset($response->testInvestigation)) {
                $case->setEntries('testInvestigation', $response->testInvestigation);
            }

            $failEntry = $case->getEntries('fail');

            if (isset($failEntry)) {
                $case->unsetEntries('fail');
            }

            $origGuarantee = $case->getOrigData('guarantee');
            $newGuarantee = $case->getData('guarantee');
            $origScore = (int) $case->getOrigData('score');
            $newScore = (int) $case->getData('score');

            if (empty($origGuarantee) == false && $origGuarantee != 'N/A' && $origGuarantee != $newGuarantee ||
                $origScore > 0 && $origScore != $newScore) {
                $message = "Signifyd: case reviewed " .
                    "from {$origGuarantee} ({$origScore}) " .
                    "to {$newGuarantee} ({$newScore})";

                $order = $this->orderFactory->create();
                $this->signifydOrderResourceModel->load($order, $case->getData('order_id'));
                $this->orderHelper->addCommentToStatusHistory($order, $message);
            }
        } catch (\Exception $e) {
            $this->logger->critical($e->__toString(), ['entity' => $case]);
            return $case;
        }

        return $case;
    }
}
