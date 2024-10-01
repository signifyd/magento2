<?php

namespace Signifyd\Connect\Test\Integration\Cases\Model\Api;

use Signifyd\Connect\Test\Integration\Cases\Cron\CreateTest;

class SignifydClientTest extends CreateTest
{
    public function testCronCreateCase()
    {
        // Bypassing test
    }

    /**
     * @magentoDataFixture configFixture
     */
    public function testSignifydClient()
    {
        $signifydClient = $this->objectManager->create(\Signifyd\Connect\Model\Api\SignifydClient::class);
        $signifydClientData = $signifydClient();

        //validate required fields
        $this->assertTrue(isset($signifydClientData['application']));
        $this->assertTrue(isset($signifydClientData['version']));
    }
}
