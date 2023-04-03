<?php

namespace Signifyd\Connect\Test\Integration\Cases\Order;

use Signifyd\Connect\Model\Casedata;
use Signifyd\Connect\Test\Integration\Cases\Cron\CreateTest;

class PaymentMappingTest extends CreateTest
{
    public function testCronCreateCase()
    {
        // Bypassing test
    }

    /**
     * @magentoDataFixture configFixture
     */
    public function testPaymentMappingAction()
    {
        /** @var \Magento\Framework\App\Config\Storage\WriterInterface $writerInterface */
        $writerInterface = $this->objectManager->create(\Magento\Framework\App\Config\Storage\WriterInterface::class);
        $arrayMapping = ["CREDIT_CARD" => ["banktransfer"]];
        $writerInterface->save('signifyd/general/payment_methods_config', json_encode($arrayMapping));

        $this->processReviewCase();
        $order = $this->getOrder();
        $orderData = $this->purchaseHelper->processOrderData($order);

        $this->assertEquals($orderData['transactions'][0]['paymentMethod'], 'CREDIT_CARD');
    }
}
