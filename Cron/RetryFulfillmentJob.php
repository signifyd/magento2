<?php

namespace Signifyd\Connect\Cron;

use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\Fulfillment\FulfillmentsToRetryFactory;
use Signifyd\Connect\Model\ProcessCron\FulfillmentFactory;

class RetryFulfillmentJob
{
    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var FulfillmentsToRetryFactory
     */
    protected $fulfillmentsToRetryFactory;

    /**
     * @var FulfillmentFactory
     */
    protected $fulfillmentFactory;

    /**
     * RetryFulfillmentJob constructor.
     * @param Logger $logger
     * @param FulfillmentsToRetryFactory $fulfillmentsToRetryFactory
     * @param FulfillmentFactory $fulfillmentFactory
     */
    public function __construct(
        Logger $logger,
        FulfillmentsToRetryFactory $fulfillmentsToRetryFactory,
        FulfillmentFactory $fulfillmentFactory
    ) {
        $this->logger = $logger;
        $this->fulfillmentsToRetryFactory = $fulfillmentsToRetryFactory;
        $this->fulfillmentFactory = $fulfillmentFactory;
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

        $this->logger->debug("CRON: Retry fulfillment method ended");
    }
}
