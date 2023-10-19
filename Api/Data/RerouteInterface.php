<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Signifyd\Connect\Api\Data;

interface RerouteInterface
{

    const ORDER_ID = 'order_id';
    const REROUTE_ID = 'reroute_id';
    const MAGENTO_STATUS = 'magento_status';
    const RETRIES = 'retries';
    const INSERTED_AT = 'inserted_at';

    /**
     * Get reroute_id
     * @return string|null
     */
    public function getRerouteId();

    /**
     * Set reroute_id
     * @param string $rerouteId
     * @return \Signifyd\Connect\Reroute\Api\Data\RerouteInterface
     */
    public function setRerouteId($rerouteId);

    /**
     * Get order_id
     * @return string|null
     */
    public function getOrderId();

    /**
     * Set order_id
     * @param string $orderId
     * @return \Signifyd\Connect\Reroute\Api\Data\RerouteInterface
     */
    public function setOrderId($orderId);

    /**
     * Get magento_status
     * @return string|null
     */
    public function getMagentoStatus();

    /**
     * Set magento_status
     * @param string $magentoStatus
     * @return \Signifyd\Connect\Reroute\Api\Data\RerouteInterface
     */
    public function setMagentoStatus($magentoStatus);

    /**
     * Get retries
     * @return string|null
     */
    public function getRetries();

    /**
     * Set retries
     * @param string $retries
     * @return \Signifyd\Connect\Reroute\Api\Data\RerouteInterface
     */
    public function setRetries($retries);

    /**
     * Get inserted_at
     * @return string|null
     */
    public function getInsertedAt();

    /**
     * Set inserted_at
     * @param string $insertedAt
     * @return \Signifyd\Connect\Reroute\Api\Data\RerouteInterface
     */
    public function setInsertedAt($insertedAt);
}