<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Signifyd\Connect\Api\Data;

interface RerouteInterface
{
    public const ORDER_ID = 'order_id';
    public const REROUTE_ID = 'reroute_id';
    public const MAGENTO_STATUS = 'magento_status';
    public const RETRIES = 'retries';
    public const INSERTED_AT = 'inserted_at';

    /**
     * Get reroute id method.
     *
     * @return string|null
     */
    public function getRerouteId();

    /**
     * Set reroute id method.
     *
     * @param string $rerouteId
     * @return $this
     */
    public function setRerouteId($rerouteId);

    /**
     * Get order idmethod.
     *
     * @return string|null
     */
    public function getOrderId();

    /**
     * Set order idmethod.
     *
     * @param string $orderId
     * @return $this
     */
    public function setOrderId($orderId);

    /**
     * Get magento status method.
     *
     * @return string|null
     */
    public function getMagentoStatus();

    /**
     * Set magento status method.
     *
     * @param string $magentoStatus
     * @return $this
     */
    public function setMagentoStatus($magentoStatus);

    /**
     * Get retries method.
     *
     * @return string|null
     */
    public function getRetries();

    /**
     * Set retries method.
     *
     * @param string $retries
     * @return $this
     */
    public function setRetries($retries);

    /**
     * Get inserted at method.
     *
     * @return string|null
     */
    public function getInsertedAt();

    /**
     * Set inserted at method.
     *
     * @param string $insertedAt
     * @return $this
     */
    public function setInsertedAt($insertedAt);
}
