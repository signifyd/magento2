<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Signifyd\Connect\Api\Data;

interface LogsSearchResultsInterface extends \Magento\Framework\Api\SearchResultsInterface
{

    /**
     * Get logs list.
     * @return \Signifyd\Connect\Api\Data\LogsInterface[]
     */
    public function getItems();

    /**
     * Set created_at list.
     * @param \Signifyd\Connect\Api\Data\LogsInterface[] $items
     * @return $this
     */
    public function setItems(array $items);
}
