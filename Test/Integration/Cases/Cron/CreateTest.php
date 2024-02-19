<?php

namespace Signifyd\Connect\Test\Integration\Cases\Cron;

use Signifyd\Connect\Test\Integration\OrderTestCase;
use Signifyd\Connect\Model\Casedata;
use Signifyd\Connect\Model\Casedata\UpdateCaseV2Factory;
use Signifyd\Connect\Model\Casedata\UpdateCaseFactory;
use Signifyd\Connect\Model\UpdateOrderFactory;
use Signifyd\Connect\Model\LogsFile;
use Magento\Framework\Filesystem;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Driver\File as DriverFile;

class CreateTest extends OrderTestCase
{
    /**
     * @var \Signifyd\Connect\Cron\RetryCaseJob
     */
    public $retryCaseJob;

    public $paymentMethod = 'banktransfer';

    public function setUp(): void
    {
        parent::setUp();

        $this->retryCaseJob = $this->objectManager->create(\Signifyd\Connect\Cron\RetryCaseJob::class);
        $this->updateCaseFactory = $this->objectManager->create(UpdateCaseFactory::class);
        $this->updateOrderFactory = $this->objectManager->create(UpdateOrderFactory::class);
        $this->logsFile = $this->objectManager->create(LogsFile::class);
        $this->filesystem = $this->objectManager->create(Filesystem::class);
        $this->directoryList = $this->objectManager->get(DirectoryList::class);
        $this->driverFile = $this->objectManager->create(DriverFile::class);
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

        $arrayToUpdateCase = [
            "signifydId" => $case->getCode(),
            "orderId" => "XGR-1840823423",
            "decision" => [
                "createdAt" => "2021-06-21T14:12:47+0000",
                "checkpointAction" => $checkpointAction,
                "score" => $score
            ]
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
