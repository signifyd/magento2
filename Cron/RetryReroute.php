<?php

namespace Signifyd\Connect\Cron;

use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\Reroute\ReroutesToRetry;
use Signifyd\Connect\Model\ProcessCron\Reroute as ProcessCronReroute;

class RetryReroute
{
    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var ReroutesToRetry
     */
    protected $reroutesToRetry;

    /**
     * @var ProcessCronReroute
     */
    protected $processCronReroute;

    /**
     * RetryFulfillmentJob constructor.
     * @param Logger $logger
     * @param ReroutesToRetry $reroutesToRetry
     * @param ProcessCronReroute $processCronReroute
     */
    public function __construct(
        Logger $logger,
        ReroutesToRetry $reroutesToRetry,
        ProcessCronReroute $processCronReroute
    ) {
        $this->logger = $logger;
        $this->reroutesToRetry = $reroutesToRetry;
        $this->processCronReroute = $processCronReroute;
    }

    /**
     * @return void
     */
    public function execute()
    {
        $this->logger->debug("CRON: Retry Reroute method called");
        $reroutesToRetry = ($this->reroutesToRetry)();

        foreach ($reroutesToRetry as $rerouteToRetry) {
            ($this->processCronReroute)($rerouteToRetry);
        }

        $this->logger->debug("CRON: Retry Reroute method ended");
    }
}
