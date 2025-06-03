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
class UpdateCase
{
    /**
     * @var ConfigHelper
     */
    public $configHelper;

    /**
     * @var OrderHelper
     */
    public $orderHelper;

    /**
     * @var Logger
     */
    public $logger;

    /**
     * @var OrderFactory
     */
    public $orderFactory;

    /**
     * @var SignifydOrderResourceModel
     */
    public $signifydOrderResourceModel;

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
     * Invoke method.
     *
     * @param mixed $case
     * @param mixed $response
     * @return mixed
     */
    public function __invoke($case, $response)
    {
        try {
            $isScoreOnly = $this->configHelper->isScoreOnly();
            $caseScore = $case->getScore();

            if (isset($response->decision)) {
                if (is_array($response->decision)) {
                    $score = $response->decision['score'] ?? null;
                    $checkpointAction = $response->decision['checkpointAction'] ?? null;
                    $checkpointActionReason = $response->decision['checkpointActionReason'] ?? null;
                } else {
                    $score = $response->decision->score ?? null;
                    $checkpointAction = $response->decision->checkpointAction ?? null;
                    $checkpointActionReason = $response->decision->checkpointActionReason ?? null;
                }
            }

            if (isset($caseScore) && $isScoreOnly) {
                $case->setMagentoStatus(Casedata::COMPLETED_STATUS);
            }

            if (isset($score) && $case->getScore() !== $score) {
                $case->setScore(floor($score));
            }

            if (isset($checkpointAction) && $case->getGuarantee() != $checkpointAction) {
                $case->setGuarantee($checkpointAction);
            }

            if (isset($checkpointActionReason) && $case->getCheckpointActionReason() != $checkpointActionReason) {
                $case->setCheckpointActionReason($checkpointActionReason);
            }

            if (isset($response->signifydId)) {
                $case->setCode($response->signifydId);
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
