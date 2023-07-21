<?php
/**
 * Copyright 2015 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Signifyd\Connect\Model;

use Signifyd\Connect\Helper\ConfigHelper;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\ResourceModel\Order as OrderResourceModel;
use Signifyd\Connect\Logger\Logger;
use Magento\Framework\Serialize\SerializerInterface;
use Signifyd\Connect\Model\ResourceModel\Order as SignifydOrderResourceModel;

/**
 * ORM model declaration for case data
 */
class Casedata extends AbstractModel
{
    /* The status when a case is created */
    const WAITING_SUBMISSION_STATUS = "waiting_submission";

    /* The status for a case when the first response from Signifyd is received */
    const IN_REVIEW_STATUS = "in_review";

    /* The status for a case that is completed */
    const COMPLETED_STATUS = "completed";

    /* The status for a case that is awiting async payment methods to finish */
    const ASYNC_WAIT = "async_wait";

    /* The status for new case */
    const NEW = "new";

    /* Synchronous response */
    const PRE_AUTH = "pre_auth";

    /* Asynchronous response */
    const POST_AUTH = "post_auth";

    /* Awaiting payment response */
    const AWAITING_PSP = "awaiting_psp";

    /**
     * @var ConfigHelper
     */
    protected $configHelper;

    /**
     * @var OrderFactory
     */
    protected $orderFactory;

    /**
     * @var OrderResourceModel
     */
    protected $orderResourceModel;

    /**
     * @var \Magento\Sales\Model\Order
     */
    protected $order;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var SerializerInterface
     */
    protected $serializer;

    /**
     * @var SignifydOrderResourceModel
     */
    protected $signifydOrderResourceModel;

    /**
     * Casedata constructor.
     * @param Context $context
     * @param Registry $registry
     * @param ConfigHelper $configHelper
     * @param ObjectManagerInterface $objectManager
     * @param OrderFactory $orderFactory
     * @param OrderResourceModel $orderResourceModel
     * @param Logger $logger
     * @param SerializerInterface $serializer
     * @param SignifydOrderResourceModel $signifydOrderResourceModel
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ConfigHelper $configHelper,
        OrderFactory $orderFactory,
        OrderResourceModel $orderResourceModel,
        Logger $logger,
        SerializerInterface $serializer,
        SignifydOrderResourceModel $signifydOrderResourceModel
    ) {
        $this->configHelper = $configHelper;
        $this->orderFactory = $orderFactory;
        $this->orderResourceModel = $orderResourceModel;
        $this->logger = $logger;
        $this->serializer = $serializer;
        $this->signifydOrderResourceModel = $signifydOrderResourceModel;

        parent::__construct($context, $registry);
    }

    /**
     * Constructor
     *
     * @return void
     */
    protected function _construct()
    {
        parent::_construct();
        $this->_init(\Signifyd\Connect\Model\ResourceModel\Casedata::class);
    }

    public function getOrder($forceLoad = false, $loadForUpdate = false)
    {
        if (isset($this->order) === false || $forceLoad) {
            $orderId = $this->getData('order_id');

            if (empty($orderId) == false) {
                $this->order = $this->orderFactory->create();

                if ($loadForUpdate === true) {
                    $this->signifydOrderResourceModel->loadForUpdate($this->order, $orderId);
                } else {
                    $this->signifydOrderResourceModel->load($this->order, $orderId);
                }
            }
        }

        return $this->order;
    }

    /**
     * @param null $index
     * @return array|mixed|null
     */
    public function getEntries($index = null)
    {
        $entries = $this->getData('entries_text');

        if (!empty($entries)) {
            try {
                $entries = $this->serializer->unserialize($entries);
            } catch (\InvalidArgumentException $e) {
                $entries = [];
            }
        }

        if (!is_array($entries)) {
            $entries = [];
        }

        if (!empty($index)) {
            return isset($entries[$index]) ? $entries[$index] : null;
        }

        return $entries;
    }

    /**
     * @param $index
     * @param $value
     * @return $this
     */
    public function setEntries($index, $value = null)
    {
        if (is_array($index)) {
            $entries = $index;
        } elseif (is_string($index)) {
            $entries = $this->getEntries();
            $entries[$index] = $value;
        }

        $entries = $this->serializer->serialize($entries);
        $this->setData('entries_text', $entries);

        return $this;
    }

    /**
     * @param $index
     * @return $this
     */
    public function unsetEntries($index)
    {
        $entries = $this->getData('entries_text');

        if (!empty($entries)) {
            try {
                $entries = $this->serializer->unserialize($entries);
            } catch (\InvalidArgumentException $e) {
                $entries = [];
            }
        }

        if (!is_array($entries)) {
            return $this;
        }

        if (!empty($index)) {
            if (isset($entries[$index])) {
                unset($entries[$index]);
            }
        }

        $entries = empty($entries) ? "" : $this->serializer->serialize($entries);
        $this->setData('entries_text', $entries);

        return $this;
    }

    /**
     * Everytime a update is triggered reset retries
     *
     * @param $updated
     * @return mixed
     */
    public function setUpdated($updated = null)
    {
        if (empty($updated)) {
            $updated = date('Y-m-d H:i:s', time());
        }

        $this->setRetries(0);

        return parent::setUpdated($updated);
    }
}
