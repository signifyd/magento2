<?php

declare(strict_types=1);

require __DIR__ . '/../order/guest_quote_with_addresses_product_simple.php';

$objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();

/** @var \Signifyd\Connect\Model\Casedata $case */
$case = $objectManager->create(\Signifyd\Connect\Model\Casedata::class);
$case->setData([
    'order_increment' => '100000002',
    // Case must be created with 60 seconds before now in order to trigger cron on retries
    'created' => strftime('%Y-%m-%d %H:%M:%S', time()-60),
    'updated' => strftime('%Y-%m-%d %H:%M:%S', time()-60),
    'magento_status' => \Signifyd\Connect\Model\Casedata::WAITING_SUBMISSION_STATUS
]);
$case->save();
