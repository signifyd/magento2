<?php

namespace Signifyd\Connect\Plugin\StripeIntegration\Payments\Helper;

use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\Payment\Stripe\Payments\RecordReturnChecker;
use Signifyd\Connect\Model\Registry;
use StripeIntegration\Payments\Helper\Webhooks as StripeWebhooks;

class Webhooks
{
    /**
     * Stripe webhook event type that makes the Stripe module create a credit memo on its own
     * when the dispute is lost.
     */
    public const DISPUTE_CLOSED_EVENT_TYPE = 'charge.dispute.closed';

    /**
     * @var Logger
     */
    public $logger;

    /**
     * @var Registry
     */
    public $registry;

    /**
     * @var HttpRequest
     */
    public $request;

    /**
     * @var JsonSerializer
     */
    public $jsonSerializer;

    /**
     * Webhooks constructor.
     *
     * @param Logger $logger
     * @param Registry $registry
     * @param HttpRequest $request
     * @param JsonSerializer $jsonSerializer
     */
    public function __construct(
        Logger $logger,
        Registry $registry,
        HttpRequest $request,
        JsonSerializer $jsonSerializer
    ) {
        $this->logger = $logger;
        $this->registry = $registry;
        $this->request = $request;
        $this->jsonSerializer = $jsonSerializer;
    }

    /**
     * Plugin around dispatch event method.
     *
     * Makes the status of a closing Stripe dispute available to
     * Signifyd\Connect\Model\Payment\Stripe\Payments\RecordReturnChecker while the event is being
     * processed. The credit memo the Stripe module creates for a lost dispute, and therefore the
     * Record Return it would trigger, happens entirely inside $proceed.
     *
     * @param StripeWebhooks $subject
     * @param callable $proceed
     * @param mixed $stdEvent
     * @param bool $processMoreThanOnce
     * @return mixed
     */
    public function aroundDispatchEvent(
        StripeWebhooks $subject,
        callable $proceed,
        $stdEvent = null,
        $processMoreThanOnce = false
    ) {
        $this->registry->setData(
            RecordReturnChecker::DISPUTE_STATUS_REGISTRY_KEY,
            $this->getDisputeStatus($stdEvent)
        );

        try {
            return $proceed($stdEvent, $processMoreThanOnce);
        } finally {
            $this->registry->setData(RecordReturnChecker::DISPUTE_STATUS_REGISTRY_KEY);
        }
    }

    /**
     * Get the dispute status from the event being dispatched.
     *
     * Returns null for any other event type, so that credit memos created by refunds keep the
     * default behavior. The event comes as an argument on the cron retry and CLI paths, and on
     * the request body when the Stripe webhook controller is the caller.
     *
     * @param mixed $stdEvent
     * @return string|null
     */
    private function getDisputeStatus($stdEvent)
    {
        try {
            if (isset($stdEvent)) {
                $event = $this->jsonSerializer->unserialize($this->jsonSerializer->serialize($stdEvent));
            } else {
                $event = $this->jsonSerializer->unserialize((string) $this->request->getContent());
            }

            if (is_array($event) === false ||
                isset($event['type']) === false ||
                $event['type'] !== self::DISPUTE_CLOSED_EVENT_TYPE
            ) {
                return null;
            }

            $disputeStatus = $event['data']['object']['status'] ?? null;

            $this->logger->debug(
                "Stripe dispute closed event received with status {$disputeStatus}"
            );

            return $disputeStatus;
        } catch (\Exception $e) {
            $this->logger->error('Failed to read Stripe dispute status: ' . $e->getMessage());
            return null;
        } catch (\Error $e) {
            $this->logger->error('Failed to read Stripe dispute status: ' . $e->getMessage());
            return null;
        }
    }
}
