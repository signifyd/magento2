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
    protected function setUp(): void
    {
        parent::setUp();

        $this->objectManager = Bootstrap::getObjectManager();
        $this->logger = $this->objectManager->create('\Signifyd\Connect\Logger\Logger');

        if (isset($this->incrementId) == false) {
            $this->incrementId = '10-' . time();
        }

        require __DIR__ . '/_files/settings/general.php';
    }

    /**
     * @return \Signifyd\Connect\Model\Casedata
     */
    public function getCase($incrementId = null)
    {
        if (empty($incrementId) == true) {
            $incrementId = $this->incrementId;
        }

        /** @var \Signifyd\Connect\Model\Casedata $case */
        $case = $this->objectManager->create(\Signifyd\Connect\Model\Casedata::class);
        $case->load($incrementId, 'order_increment');
        return $case;
    }

    /**
     * @return \Magento\Sales\Model\Order
     */
    public function getOrder()
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order = $this->objectManager->get(\Magento\Sales\Model\Order::class);
        $order->loadByIncrementId($this->incrementId);
        return $order;
    }
}
