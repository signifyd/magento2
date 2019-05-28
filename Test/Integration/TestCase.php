<?php

declare(strict_types=1);

namespace Signifyd\Connect\Test\Integration;

use Magento\TestFramework\Helper\Bootstrap;

class TestCase extends \PHPUnit\Framework\TestCase
{
    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @var \Signifyd\Connect\Logger\Logger
     */
    protected $logger;

    /**
     * @var string
     */
    protected $incrementId;

    /**
     * @inheritdoc
     */
    protected function setUp()
    {
        parent::setUp();

        $this->objectManager = Bootstrap::getObjectManager();
        $this->logger = $this->objectManager->create('\Signifyd\Connect\Logger\Logger');

        $this->incrementId = '10-' . time();

        require __DIR__ . '/_files/settings/general.php';
    }
}