<?php

namespace Signifyd\Connect\Model;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Signifyd\Connect\Logger\Logger;

class RetryModel extends AbstractHelper
{
    /**
     * @var Logger
     */
    protected $logger;
    
    protected $objectCollectionFactory;
    
    protected $objectResourceModel;

    /**
     * Retry constructor.
     * @param Context $context
     * @param Logger $logger
     */
    public function __construct(
        Context $context,
        Logger $logger
    ) {
        parent::__construct($context);

        $this->logger = $logger;
    }

    /**
     * @param $status
     * @return mixed
     */
    public function __invoke()
    {
        $retryTimes = $this->calculateRetryTimes();

        $time = time();
        $lastTime = $time - (end($retryTimes) + 60*60*24);
        $current = date('Y-m-d H:i:s', $time);
        $from = date('Y-m-d H:i:s', $lastTime);

        $objectCollection = $this->objectCollectionFactory->create();
        $objectCollection->addFieldToFilter('inserted_at', ['gteq' => $from]);
        $objectCollection->addFieldToFilter('magento_status', ['eq' => 'waiting_submission']);
        $objectCollection->addFieldToFilter('retries', ['lt' => count($retryTimes)]);
        $objectCollection->addExpressionFieldToSelect(
            'seconds_after_inserted_at',
            "TIME_TO_SEC(TIMEDIFF('{$current}', inserted_at))",
            ['inserted_at']
        );

        $objectsToRetry = [];

        foreach ($objectCollection->getItems() as $object) {
            $retries = $object->getData('retries');
            $secondsAfterUpdate = $object->getData('seconds_after_inserted_at');

            if ($secondsAfterUpdate > $retryTimes[$retries]) {
                $objectsToRetry[$object->getId()] = $object;
                $object->setData('retries', $retries + 1);
                $this->objectResourceModel->save($object);
            }
        }

        return $objectsToRetry;
    }

    /**
     * Retry times calculated from last update
     *
     * @return array
     */
    protected function calculateRetryTimes()
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
