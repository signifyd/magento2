<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Signifyd\Connect\Model;

use Magento\Framework\Model\AbstractModel;
use Signifyd\Connect\Api\Data\RerouteInterface;

class Reroute extends AbstractModel implements RerouteInterface
{

    /**
     * @inheritDoc
     */
    public function _construct()
    {
        $this->_init(\Signifyd\Connect\Model\ResourceModel\Reroute::class);
    }

    /**
     * @inheritDoc
     */
    public function getRerouteId()
    {
        return $this->getData(self::REROUTE_ID);
    }

    /**
     * @inheritDoc
     */
    public function setRerouteId($rerouteId)
    {
        return $this->setData(self::REROUTE_ID, $rerouteId);
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
    public function getMagentoStatus()
    {
        return $this->getData(self::MAGENTO_STATUS);
    }

    /**
     * @inheritDoc
     */
    public function setMagentoStatus($magentoStatus)
    {
        return $this->setData(self::MAGENTO_STATUS, $magentoStatus);
    }

    /**
     * @inheritDoc
     */
    public function getRetries()
    {
        return $this->getData(self::RETRIES);
    }

    /**
     * @inheritDoc
     */
    public function setRetries($retries)
    {
        return $this->setData(self::RETRIES, $retries);
    }

    /**
     * @inheritDoc
     */
    public function getInsertedAt()
    {
        return $this->getData(self::INSERTED_AT);
    }

    /**
     * @inheritDoc
     */
    public function setInsertedAt($insertedAt)
    {
        return $this->setData(self::INSERTED_AT, $insertedAt);
    }
}
