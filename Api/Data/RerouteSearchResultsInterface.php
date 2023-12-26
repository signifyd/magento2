<?php
/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Signifyd\Connect\Api\Data;

interface RerouteSearchResultsInterface extends \Magento\Framework\Api\SearchResultsInterface
{

    /**
     * Get reroute list.
     * @return \Signifyd\Connect\Api\Data\RerouteInterface[]
     */
    public function getItems();

    /**
     * Set order_id list.
     * @param \Signifyd\Connect\Api\Data\RerouteInterface[] $items
     * @return $this
     */
    public function setItems(array $items);
}
