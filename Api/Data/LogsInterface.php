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
     * @return \Signifyd\Connect\Logs\Api\Data\LogsInterface
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
     * @return \Signifyd\Connect\Logs\Api\Data\LogsInterface
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
     * @return \Signifyd\Connect\Logs\Api\Data\LogsInterface
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
     * @return \Signifyd\Connect\Logs\Api\Data\LogsInterface
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
     * @return \Signifyd\Connect\Logs\Api\Data\LogsInterface
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
     * @return \Signifyd\Connect\Logs\Api\Data\LogsInterface
     */
    public function setEntry($entry);
}