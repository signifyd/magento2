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
    public function getCase(array $data = [])
    {
        /** @var \Signifyd\Connect\Model\Casedata $case */
        $case = $this->objectManager->create(\Signifyd\Connect\Model\Casedata::class);

        if (empty($data) === false) {
            if (array_key_exists('order_increment', $data)) {
                $case->load($data['order_increment'], 'order_increment');
            } elseif (array_key_exists('entity_id', $data)) {
                $case->load($data['entity_id'], 'entity_id');
            }
        } else {
            $incrementId = $this->incrementId;
            $case->load($incrementId, 'order_increment');
        }

        return $case;
    }

    /**
     * @return \Signifyd\Connect\Model\Fulfillment
     */
    public function getFulfillment(array $data = [])
    {
        /** @var \Signifyd\Connect\Model\Fulfillment $fulfillment */
        $fulfillment = $this->objectManager->create(\Signifyd\Connect\Model\Fulfillment::class);

        if (empty($data) === false) {
            if (array_key_exists('order_increment', $data)) {
                $fulfillment->load($data['order_increment'], 'order_id');
            } elseif (array_key_exists('entity_id', $data)) {
                $fulfillment->load($data['entity_id'], 'entity_id');
            }
        } else {
            $incrementId = $this->incrementId;
            $fulfillment->load($incrementId, 'order_id');
        }

        return $fulfillment;
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
