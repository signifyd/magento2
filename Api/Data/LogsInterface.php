<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Signifyd\Connect\Api\Data;

interface LogsInterface
{
    public const LOGS_ID = 'logs_id';
    public const TYPE = 'type';
    public const ORDER_ID = 'order_id';
    public const QUOTE_ID = 'quote_id';
    public const ENTRY = 'entry';
    public const CREATED_AT = 'created_at';

    /**
     * Get logs id method
     *
     * @return string|null
     */
    public function getLogsId();

    /**
     * Set logs id method
     *
     * @param string $logsId
     * @return $this
     */
    public function setLogsId($logsId);

    /**
     * Get created at method
     *
     * @return string|null
     */
    public function getCreatedAt();

    /**
     * Set created at method
     *
     * @param string $createdAt
     * @return $this
     */
    public function setCreatedAt($createdAt);

    /**
     * Get quote id method
     *
     * @return string|null
     */
    public function getQuoteId();

    /**
     * Set quote id method
     *
     * @param string $quoteId
     * @return $this
     */
    public function setQuoteId($quoteId);

    /**
     * Get order id method
     *
     * @return string|null
     */
    public function getOrderId();

    /**
     * Set order id method
     *
     * @param string $orderId
     * @return $this
     */
    public function setOrderId($orderId);

    /**
     * Get type method
     *
     * @return string|null
     */
    public function getType();

    /**
     * Set type method
     *
     * @param string $type
     * @return $this
     */
    public function setType($type);

    /**
     * Get entry method
     *
     * @return string|null
     */
    public function getEntry();

    /**
     * Set entry method
     *
     * @param string $entry
     * @return $this
     */
    public function setEntry($entry);
}
