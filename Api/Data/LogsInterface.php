<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Signifyd\Connect\Api\Data;

interface LogsInterface
{

    const LOGS_ID = 'logs_id';
    const TYPE = 'type';
    const ORDER_ID = 'order_id';
    const QUOTE_ID = 'quote_id';
    const ENTRY = 'entry';
    const CREATED_AT = 'created_at';

    /**
     * Get logs_id
     * @return string|null
     */
    public function getLogsId();

    /**
     * Set logs_id
     * @param string $logsId
     * @return $this
     */
    public function setLogsId($logsId);

    /**
     * Get created_at
     * @return string|null
     */
    public function getCreatedAt();

    /**
     * Set created_at
     * @param string $createdAt
     * @return $this
     */
    public function setCreatedAt($createdAt);

    /**
     * Get quote_id
     * @return string|null
     */
    public function getQuoteId();

    /**
     * Set quote_id
     * @param string $quoteId
     * @return $this
     */
    public function setQuoteId($quoteId);

    /**
     * Get order_id
     * @return string|null
     */
    public function getOrderId();

    /**
     * Set order_id
     * @param string $orderId
     * @return $this
     */
    public function setOrderId($orderId);

    /**
     * Get type
     * @return string|null
     */
    public function getType();

    /**
     * Set type
     * @param string $type
     * @return $this
     */
    public function setType($type);

    /**
     * Get entry
     * @return string|null
     */
    public function getEntry();

    /**
     * Set entry
     * @param string $entry
     * @return $this
     */
    public function setEntry($entry);
}
