<?php

namespace Signifyd\Connect\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Signifyd\Connect\Model\ResourceModel\Casedata\CollectionFactory as CasedataCollectionFactory;
use Signifyd\Connect\Model\ResourceModel\Casedata as CasedataResourceModel;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\CasedataFactory;

class Retry extends AbstractHelper
{
    /**
     * @var CasedataCollectionFactory
     */
    protected $casedataCollectionFactory;

    /**
     * @var CasedataResourceModel
     */
    protected $casedataResourceModel;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var CasedataFactory
     */
    protected $casedataFactory;

    /**
     * @var ConfigHelper
     */
    protected $configHelper;

    /**
     * Retry constructor.
     * @param Context $context
     * @param CasedataCollectionFactory $casedataCollectionFactory
     * @param CasedataResourceModel $casedataResourceModel
     * @param Logger $logger
     * @param CasedataFactory $casedataFactory
     * @param ConfigHelper $configHelper
     */
    public function __construct(
        Context $context,
        CasedataCollectionFactory $casedataCollectionFactory,
        CasedataResourceModel $casedataResourceModel,
        Logger $logger,
        CasedataFactory $casedataFactory,
        ConfigHelper $configHelper
    ) {
        parent::__construct($context);

        $this->casedataCollectionFactory = $casedataCollectionFactory;
        $this->casedataResourceModel = $casedataResourceModel;
        $this->logger = $logger;
        $this->casedataFactory = $casedataFactory;
        $this->configHelper = $configHelper;
    }

    /**
     * @param $status
     * @return mixed
     */
    public function getRetryCasesByStatus($status)
    {
        $retryTimes = $this->calculateRetryTimes();

        $time = time();
        $lastTime = $time - (end($retryTimes) + 60*60*24);
        $current = date('Y-m-d H:i:s', $time);
        $from = date('Y-m-d H:i:s', $lastTime);

        /** @var  \Signifyd\Connect\Model\ResourceModel\Casedata\Collection $casesCollection */
        $casesCollection = $this->casedataCollectionFactory->create();
        $casesCollection->addFieldToFilter('updated', ['gteq' => $from]);
        $casesCollection->addFieldToFilter('magento_status', ['eq' => $status]);
        $casesCollection->addFieldToFilter('retries', ['lt' => count($retryTimes)]);
        $casesCollection->addExpressionFieldToSelect(
            'seconds_after_update',
            "TIME_TO_SEC(TIMEDIFF('{$current}', updated))",
            ['updated']
        );

        $cronBatchSize = $this->configHelper->getCronBatchSize();

        if (isset($cronBatchSize) && is_numeric($cronBatchSize)) {
            $casesCollection->setPageSize((int)$cronBatchSize);
            $casesCollection->setOrder('updated', 'ASC');
        }

        $casesToRetry = [];

        /** @var \Signifyd\Connect\Model\Casedata $case */
        foreach ($casesCollection->getItems() as $case) {
            $caseToUpdate = $this->casedataFactory->create();
            $this->casedataResourceModel->loadForUpdate($caseToUpdate, $case->getId());

            $retries = $caseToUpdate->getData('retries');
            $secondsAfterUpdate = $case->getData('seconds_after_update');

            if ($secondsAfterUpdate > $retryTimes[$retries]) {

                $casesToRetry[$caseToUpdate->getId()] = $caseToUpdate;
                $caseToUpdate->setData('retries', $retries + 1);
                $this->casedataResourceModel->save($caseToUpdate);
            }
        }

        return $casesToRetry;
    }

    /**
     * Retry times calculated from last update
     *
     * @return array
     */
    public function calculateRetryTimes()
    {
        $retryTimes = [];

        for ($retry = 0; $retry < 15; $retry++) {
            // Increment retry times exponentially
            $retryTimes[$retry] = 20 * pow(2, $retry);
            // Increment should not be greater than one day
            $retryTimes[$retry] = $retryTimes[$retry] > 86400 ? 86400 : $retryTimes[$retry];
            // Sum retry time to previous, calculating total time to wait from last update
            $retryTimes[$retry] += isset($retryTimes[$retry-1]) ? $retryTimes[$retry-1] : 0;
        }

        return $retryTimes;
    }
}
