<?php

declare(strict_types=1);

$objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();

/** @var \Signifyd\Connect\Model\Casedata $case */
$case = $objectManager->create(\Signifyd\Connect\Model\Casedata::class);
$case->setData([
    'order_increment' => '100000002',
    'created' => '2019-05-03 00:01:00',
    'updated' => '2019-05-03 00:01:00',
    'magento_status' => \Signifyd\Connect\Model\Casedata::IN_REVIEW_STATUS
]);
$case->save();
