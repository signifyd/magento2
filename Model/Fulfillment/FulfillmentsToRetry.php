<?php

namespace Signifyd\Connect\Model\Fulfillment;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\ResourceModel\Fulfillment\CollectionFactory as FulfillmentCollectionFactory;
use Signifyd\Connect\Model\ResourceModel\Fulfillment as FulfillmentResourceModel;
use Signifyd\Connect\Model\RetryModel;

class FulfillmentsToRetry extends RetryModel
{
    /**
     * @var Logger
     */
    public $logger;

    /**
     * @var FulfillmentCollectionFactory
     */
    public $objectCollectionFactory;

    /**
     * @var FulfillmentResourceModel
     */
    public $objectResourceModel;

    /**
     * Retry constructor.
     * @param Context $context
     * @param Logger $logger
     * @param FulfillmentCollectionFactory $objectCollectionFactory
     * @param FulfillmentResourceModel $objectResourceModel
     */
    public function __construct(
        Context $context,
        Logger $logger,
        FulfillmentCollectionFactory $objectCollectionFactory,
        FulfillmentResourceModel $objectResourceModel
    ) {
        parent::__construct($context, $logger);

        $this->objectCollectionFactory = $objectCollectionFactory;
        $this->objectResourceModel = $objectResourceModel;
    }
}
