<?php

namespace Signifyd\Connect\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Signifyd\Connect\Model\ResourceModel\Casedata\CollectionFactory as CasedataCollectionFactory;
use Signifyd\Connect\Model\ResourceModel\Casedata as CasedataResourceModel;
use Signifyd\Connect\Logger\Logger;

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
     * Retry constructor.
     * @param Context $context
     * @param CasedataCollectionFactory $casedataCollectionFactory
     * @param CasedataResourceModel $casedataResourceModel
     * @param Logger $logger
     */
    public function __construct(
        Context $context,
        CasedataCollectionFactory $casedataCollectionFactory,
        CasedataResourceModel $casedataResourceModel,
        Logger $logger
    ) {
        parent::__construct($context);

        $this->casedataCollectionFactory = $casedataCollectionFactory;
        $this->casedataResourceModel = $casedataResourceModel;
        $this->logger = $logger;
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

        $casesCollection = $this->casedataCollectionFactory->create();
        $casesCollection->addFieldToFilter('updated', ['gteq' => $from]);
        $casesCollection->addFieldToFilter('magento_status', ['eq' => $status]);
        $casesCollection->addFieldToFilter('retries', ['lt' => count($retryTimes)]);
        $casesCollection->addExpressionFieldToSelect(
            'seconds_after_update',
            "TIME_TO_SEC(TIMEDIFF('{$current}', updated))",
            ['updated']
        );

        $casesToRetry = [];

        /** @var \Signifyd\Connect\Model\Casedata $case */
        foreach ($casesCollection->getItems() as $case) {
            $retries = $case->getData('retries');
            $secondsAfterUpdate = $case->getData('seconds_after_update');

            if ($secondsAfterUpdate > $retryTimes[$retries]) {
                $casesToRetry[$case->getId()] = $case;
                $case->setData('retries', $retries + 1);
                $this->casedataResourceModel->save($case);
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
