<?php

namespace Signifyd\Connect\Test\Integration\Cases\Cron;

use Signifyd\Connect\Model\Casedata;

class ReviewTest extends CreateTest
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
        $this->placeQuote($this->getQuote('guest_quote'));

        $this->updateCaseForRetry();

        $this->tryToReviewCase();

        $case = $this->getCase();

        $this->assertEquals(Casedata::COMPLETED_STATUS, $case->getData('magento_status'));
        $this->assertNotEmpty($case->getData('score'));
    }

    /**
     * Try to review case three times and fail if not succeed
     *
     * @param int $retry
     * @return bool
     */
    public function tryToReviewCase($retry = 0)
    {
        $this->retryCaseJob->execute();

        $case = $this->getCase();

        $guarantee = $case->getGuarantee();
        $score = $case->getScore();

        if (empty($guarantee) == false && $guarantee != 'N/A' && empty($score) == false) {
            return true;
        } elseif ($retry < 2) {
            // Avoid do not process on cron because of exponential timeouts
            $this->updateCaseForRetry($case);

            // Give Signifyd some time to review case
            sleep(20);

            return $this->tryToReviewCase($retry+1);
        } else {
            return false;
        }
    }

    public function updateCaseForRetry($case = null)
    {
        $case = empty($case) ? $this->getCase() : $case;
        $case->setUpdated(date('Y-m-d H:i:s', time()-60));
        $case->save();
    }
}
