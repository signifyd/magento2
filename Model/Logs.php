<?php

declare(strict_types=1);

namespace Signifyd\Connect\Model;

use Magento\Framework\Model\AbstractModel;
use Signifyd\Connect\Api\Data\LogsInterface;
use Magento\Framework\Serialize\SerializerInterface;

class Logs extends AbstractModel implements LogsInterface
{
    /**
     * @var SerializerInterface
     */
    protected $serializer;

    /**
     * @param SerializerInterface $serializer
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        SerializerInterface $serializer,
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->serializer = $serializer;

        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }

    /**
     * @inheritDoc
     */
    public function _construct()
    {
        $this->_init(\Signifyd\Connect\Model\ResourceModel\Logs::class);
    }

    /**
     * @inheritDoc
     */
    public function getLogsId()
    {
        return $this->getData(self::LOGS_ID);
    }

    /**
     * @inheritDoc
     */
    public function setLogsId($logsId)
    {
        return $this->setData(self::LOGS_ID, $logsId);
    }

    /**
     * @inheritDoc
     */
    public function getCreatedAt()
    {
        return $this->getData(self::CREATED_AT);
    }

    /**
     * @inheritDoc
     */
    public function setCreatedAt($createdAt)
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }

    /**
     * @inheritDoc
     */
    public function getQuoteId()
    {
        return $this->getData(self::QUOTE_ID);
    }

    /**
     * @inheritDoc
     */
    public function setQuoteId($quoteId)
    {
        return $this->setData(self::QUOTE_ID, $quoteId);
    }

    /**
     * @inheritDoc
     */
    public function getOrderId()
    {
        return $this->getData(self::ORDER_ID);
    }

    /**
     * @inheritDoc
     */
    public function setOrderId($orderId)
    {
        return $this->setData(self::ORDER_ID, $orderId);
    }

    /**
     * @inheritDoc
     */
    public function getType()
    {
        return $this->getData(self::TYPE);
    }

    /**
     * @inheritDoc
     */
    public function setType($type)
    {
        return $this->setData(self::TYPE, $type);
    }

    /**
     * @inheritDoc
     */
    public function getEntry()
    {
        return $this->getData(self::ENTRY);
    }

    /**
     * @inheritDoc
     */
    public function setEntry($entry)
    {
        return $this->setData(self::ENTRY, $entry);
    }
}
