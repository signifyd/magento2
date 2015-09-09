<?php

namespace Signifyd\Connect\Model\Observer;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address;
use Psr\Log\LoggerInterface;
use \Magento\Framework\ObjectManagerInterface;

/**
 * Observer for order grid building events. Appends Signifyd data
 */
class OrderGrid
{
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $_coreConfig;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $_logger;

    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $_objectManager;

    public function __construct(
        ObjectManagerInterface $objectManager,
        LoggerInterface $logger,
        ScopeConfigInterface $scopeConfig
    ) {
        try {
            $this->_logger = $logger;
            $this->_objectManager = $objectManager;
            $this->_coreConfig = $scopeConfig;
        }
        catch(\Exception $ex)
        {
            $logger->info($ex->getMessage());
        }
    }

    public function onSalesOrderGridCollectionLoadBefore(Observer $observer)
    {
        /** @var $collection \Magento\Sales\Model\Resource\Order\Grid\Collection **/
        $collection = $observer->getData('order_grid_collection');
        $select = $collection->getSelect();
        $select->joinLeft(array('signifyd' => $collection->getTable('signifyd_connect_case')),
            'signifyd.order_increment = main_table.increment_id',
            array('score' => 'score', 'guarantee' => 'guarantee'));
    }
}
