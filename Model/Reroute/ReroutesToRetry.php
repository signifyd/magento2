<?php

namespace Signifyd\Connect\Model\Reroute;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\ResourceModel\Reroute\CollectionFactory as RerouteCollectionFactory;
use Signifyd\Connect\Model\ResourceModel\Reroute as RerouteResourceModel;
use Signifyd\Connect\Model\RetryModel;

class ReroutesToRetry extends RetryModel
{
    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var RerouteCollectionFactory
     */
    protected $objectCollectionFactory;

    /**
     * @var RerouteResourceModel
     */
    protected $objectResourceModel;

    /**
     * Retry constructor.
     * @param Context $context
     * @param Logger $logger
     * @param RerouteCollectionFactory $objectCollectionFactory
     * @param RerouteResourceModel $objectResourceModel
     */
    public function __construct(
        Context $context,
        Logger $logger,
        RerouteCollectionFactory $objectCollectionFactory,
        RerouteResourceModel $objectResourceModel
    ) {
        parent::__construct($context, $logger);

        $this->objectCollectionFactory = $objectCollectionFactory;
        $this->objectResourceModel = $objectResourceModel;
    }
}
