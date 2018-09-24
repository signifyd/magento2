<?php
/**
 * Copyright 2017 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace Signifyd\Connect\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Message\ManagerInterface;
use Magento\Config\Model\Config\Factory as ConfigFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * Observer for purchase event. Sends order data to Signifyd service
 */
class Config implements ObserverInterface
{
    /**
     * @var ResourceConnection
     */
    protected $resource;

    /**
     * @var ManagerInterface
     */
    protected $messageManager;

    /**
     * @var ConfigFactory
     */
    protected $configFactory;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfigInterface;

    /**
     * List of tables and columns desired for checking on database
     * If is needed to only check table existence, leave columns array empty
     *
     * @var array
     */
    protected $desiredDatabaseStructure = array(
        'signifyd_connect_case' => array(),
        'signifyd_connect_retries' => array(),
        'sales_order' => array('signifyd_score', 'signifyd_guarantee', 'signifyd_code', 'origin_store_code'),
        'sales_order_grid' => array('signifyd_score', 'signifyd_guarantee', 'signifyd_code')
    );

    public function __construct(
        ResourceConnection $resource,
        ManagerInterface $messageManager,
        ConfigFactory $configFactory,
        ScopeConfigInterface $scopeConfigInterface
    ) {
        $this->resource = $resource;
        $this->messageManager = $messageManager;
        $this->configFactory = $configFactory;
        $this->scopeConfigInterface = $scopeConfigInterface;
    }

    public function execute(Observer $observer)
    {
        /** @var \Magento\Framework\App\Request\Http $request */
        $request = $observer->getEvent()->getData('request');
        $section = $request->getParam('section');

        if ($section == 'signifyd') {
            $connection = $this->resource->getConnection();
            $databaseFailure = false;

            foreach ($this->desiredDatabaseStructure as $table => $columns) {
                $table = $this->resource->getTableName($table);

                if (!$connection->isTableExists($table)) {
                    $databaseFailure = true;
                    break;
                }

                foreach ($columns as $column) {
                    if (!$connection->tableColumnExists($table, $column)) {
                        $databaseFailure = true;
                        break;
                    }
                }
            }

            if ($databaseFailure) {
                $this->messageManager->addError("Signifyd: There is one or more database modifications missing. " .
                    "Please, follow instructions on ‘Database Errors’ sections of " .
                    "<a href='https://github.com/signifyd/magento2/blob/master/README.md' target='_blank'>README</a> on GitHub");

                $enablePath = 'signifyd/general/enabled';
                $enableValue = $this->scopeConfigInterface->getValue($enablePath);

                if (true || $enableValue) {
                    $configData = [
                        'section' => 'signifyd',
                        'website' => null,
                        'store'   => null,
                        'groups'  => [
                            'general' => [
                                'fields' => [
                                    'enabled' => [
                                        'value' => 0,
                                    ],
                                ],
                            ],
                        ],
                    ];

                    $configModel = $this->configFactory->create(['data' => $configData]);
                    $configModel->save();
                }
            }
        }
    }
}
