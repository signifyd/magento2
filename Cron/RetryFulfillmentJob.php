<?php

namespace Signifyd\Connect\Cron;

use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\Fulfillment\FulfillmentsToRetryFactory;
use Signifyd\Connect\Model\ProcessCron\FulfillmentFactory;
use Signifyd\Connect\Model\SignifydFlags;

class RetryFulfillmentJob
{
    /**
     * @var Logger
     */
    public $logger;

    /**
     * @var FulfillmentsToRetryFactory
     */
    public $fulfillmentsToRetryFactory;

    /**
     * @var FulfillmentFactory
     */
    public $fulfillmentFactory;

    /**
     * @var SignifydFlags
     */
    public $signifydFlags;

    /**
     * RetryFulfillmentJob constructor.
     * @param Logger $logger
     * @param FulfillmentsToRetryFactory $fulfillmentsToRetryFactory
     * @param FulfillmentFactory $fulfillmentFactory
     * @param SignifydFlags $signifydFlags
     */
    public function __construct(
        Logger $logger,
        FulfillmentsToRetryFactory $fulfillmentsToRetryFactory,
        FulfillmentFactory $fulfillmentFactory,
        SignifydFlags $signifydFlags
    ) {
        $this->logger = $logger;
        $this->fulfillmentsToRetryFactory = $fulfillmentsToRetryFactory;
        $this->fulfillmentFactory = $fulfillmentFactory;
        $this->signifydFlags = $signifydFlags;
    }

    /**
     * @return void
     */
    public function execute()
    {
        $this->logger->debug("CRON: Retry fulfillment method called");

        $fulfillmentsToRetry = $this->fulfillmentsToRetryFactory->create();
        $fulfillments = $fulfillmentsToRetry();

        $processFulfillment = $this->fulfillmentFactory->create();
        $processFulfillment($fulfillments);

        $this->signifydFlags->updateCronFlag();
        $this->logger->debug("CRON: Retry fulfillment method ended");
    }
}
