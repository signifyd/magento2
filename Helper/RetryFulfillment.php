<?php

namespace Signifyd\Connect\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\ResourceModel\Fulfillment\CollectionFactory as FulfillmentCollectionFactory;
use Signifyd\Connect\Model\ResourceModel\Fulfillment as FulfillmentResourceModel;

class RetryFulfillment extends AbstractHelper
{
    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var FulfillmentCollectionFactory
     */
    protected $fulfillmentCollectionFactory;

    /**
     * @var FulfillmentResourceModel
     */
    protected $fulfillmentResourceModel;

    /**
     * Retry constructor.
     * @param Context $context
     * @param Logger $logger
     * @param FulfillmentCollectionFactory $fulfillmentCollectionFactory
     * @param FulfillmentResourceModel $fulfillmentResourceModel
     */
    public function __construct(
        Context $context,
        Logger $logger,
        FulfillmentCollectionFactory $fulfillmentCollectionFactory,
        FulfillmentResourceModel $fulfillmentResourceModel
    ) {
        parent::__construct($context);

        $this->logger = $logger;
        $this->fulfillmentCollectionFactory = $fulfillmentCollectionFactory;
        $this->fulfillmentResourceModel = $fulfillmentResourceModel;
    }

    /**
     * @param $status
     * @return mixed
     */
    public function getRetryFulfillment()
    {
        $retryTimes = $this->calculateRetryTimes();

        $time = time();
        $lastTime = $time - (end($retryTimes) + 60*60*24);
        $current = date('Y-m-d H:i:s', $time);
        $from = date('Y-m-d H:i:s', $lastTime);

        $fulfillmentsCollection = $this->fulfillmentCollectionFactory->create();
        $fulfillmentsCollection->addFieldToFilter('inserted_at', ['gteq' => $from]);
        $fulfillmentsCollection->addFieldToFilter('magento_status', ['eq' => 'waiting_submission']);
        $fulfillmentsCollection->addFieldToFilter('retries', ['lt' => count($retryTimes)]);
        $fulfillmentsCollection->addExpressionFieldToSelect(
            'seconds_after_inserted_at',
            "TIME_TO_SEC(TIMEDIFF('{$current}', inserted_at))",
            ['inserted_at']
        );

        $fulfillmentsToRetry = [];

        /** @var \Signifyd\Connect\Model\Casedata $fulfillment */
        foreach ($fulfillmentsCollection->getItems() as $fulfillment) {
            $retries = $fulfillment->getData('retries');
            $secondsAfterUpdate = $fulfillment->getData('seconds_after_inserted_at');

            if ($secondsAfterUpdate > $retryTimes[$retries]) {
                $fulfillmentsToRetry[$fulfillment->getId()] = $fulfillment;
                $fulfillment->setData('retries', $retries + 1);
                $this->fulfillmentResourceModel->save($fulfillment);
            }
        }

        return $fulfillmentsToRetry;
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
