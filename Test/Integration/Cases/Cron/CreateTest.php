<?php

namespace Signifyd\Connect\Test\Integration\Cases\Cron;

use Signifyd\Connect\Test\Integration\OrderTestCase;
use Signifyd\Connect\Model\Casedata;

class CreateTest extends OrderTestCase
{
    /**
     * @var \Signifyd\Connect\Cron\RetryCaseJob
     */
    protected $retryCaseJob;

    protected $paymentMethod = 'banktransfer';

    public function setUp(): void
    {
        parent::setUp();

        $this->retryCaseJob = $this->objectManager->create(\Signifyd\Connect\Cron\RetryCaseJob::class);
    }

    /**
     * @magentoDataFixture configFixture
     */
    public function testCronCreateCase()
    {
        $order = $this->placeQuote($this->getQuote('guest_quote'));
        /** @var \Signifyd\Connect\Model\Casedata $case */
        $case = $this->objectManager->create(Casedata::class);
        $case->setData([
            'order_increment' => $this->incrementId,
            // Case must be created with 60 seconds before now in order to trigger cron on retries
            'created' => date('Y-m-d H:i:s', time()-60),
            'updated' => date('Y-m-d H:i:s', time()-60),
            'order_id' => $order->getId(),
            'magento_status' => \Signifyd\Connect\Model\Casedata::WAITING_SUBMISSION_STATUS
        ]);
        $case->save();

        require __DIR__ . '/../../_files/settings/restrict_none_payment_methods.php';

        $this->retryCaseJob->execute();

        $case = $this->getCase();

        $this->assertEquals('PENDING', $case->getData('signifyd_status'));
        $this->assertEquals(Casedata::IN_REVIEW_STATUS, $case->getData('magento_status'));
        $this->assertNotEmpty($case->getData('code'));
    }

    public function getRequestJson($case, $isAcceptRequest = true)
    {
        if ($isAcceptRequest) {
            $checkpointAction = 'ACCEPT';
            $score = 150.0;
        } else {
            $checkpointAction = 'REJECT';
            $score = 999.0;
        }

        $arrayToUpdateCase = ["createdAt" => "2021-06-21T14:12:47+0000",
            "updatedAt" => "2021-06-21T14:12:47+0000",
            "isTest" => true,
            "score" => $score,
            "customerCaseId" => $case->getOrderIncrement(),
            "checkpointAction" => $checkpointAction,
            "caseId" => $case->getCode()
        ];

        /** @var \Magento\Framework\Serialize\Serializer\Json $jsonSerializer */
        $jsonSerializer = $this->objectManager->create(\Magento\Framework\Serialize\Serializer\Json::class);
        $updateCaseJson = $jsonSerializer->serialize($arrayToUpdateCase);

        return (object) $jsonSerializer->unserialize($updateCaseJson);
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

    public static function configFixture()
    {
        require __DIR__ . '/../../_files/order/guest_quote_with_addresses_product_simple.php';
    }
}
