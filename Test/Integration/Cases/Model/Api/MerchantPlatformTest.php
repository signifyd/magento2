<?php

namespace Signifyd\Connect\Test\Integration\Cases\Model\Api;

use Signifyd\Connect\Test\Integration\Cases\Cron\CreateTest;

class MerchantPlatformTest extends CreateTest
{
    public function testCronCreateCase()
    {
        // Bypassing test
    }

    /**
     * @magentoDataFixture configFixture
     */
    public function testMerchantPlatform()
    {
        $merchantPlatform = $this->objectManager->create(\Signifyd\Connect\Model\Api\MerchantPlatform::class);
        $merchantPlatformData = $merchantPlatform();

        //validate required fields
        $this->assertTrue(isset($merchantPlatformData['name']));
        $this->assertTrue(isset($merchantPlatformData['version']));
    }
}
