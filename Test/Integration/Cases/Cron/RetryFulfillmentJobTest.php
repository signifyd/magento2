<?php

namespace Signifyd\Connect\Test\Integration\Cases\Cron;

use Signifyd\Connect\Model\Casedata;

class RetryFulfillmentJobTest extends CreateTest
{
    public function testCronCreateCase()
    {
        // Bypassing test
    }

    /**
     * @magentoDataFixture configFixture
     */
    public function testCronReviewCase()
    {
        parent::testCronCreateCase();
        $order = $this->getOrder();
        $order->setState('processing');
        $this->createShipment($order);

        $this->updateFulfillmentForRetry();
        $this->tryToReviewFulfillment();
        $fulfillment = $this->getFulfillment();

        $this->assertEquals('completed', $fulfillment->getData('magento_status'));
    }

    /**
     * Try to review case three times and fail if not succeed
     *
     * @param int $retry
     * @return bool
     */
    public function tryToReviewFulfillment($retry = 0)
    {
        $this->retryFulfillmentJob->execute();

        $fulfillment = $this->getFulfillment();

        $magentoStatus = $fulfillment->getMagentoStatus();

        if ($magentoStatus == 'completed') {
            return true;
        } elseif ($retry < 2 && $magentoStatus == 'waiting_submission') {
            // Avoid do not process on cron because of exponential timeouts
            $this->updateFulfillmentForRetry();

            // Give Signifyd some time to review case
            sleep(5);

            return $this->tryToReviewFulfillment($retry+1);
        } else {
            return false;
        }
    }

    public function updateFulfillmentForRetry($fulfillment = null)
    {
        $fulfillment = empty($fulfillment) ? $this->getFulfillment() : $fulfillment;
        $fulfillment->setInsertedAt(strftime('%Y-%m-%d %H:%M:%S', time()-60));
        $fulfillment->save();
    }
}
