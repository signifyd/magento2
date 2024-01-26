<?php

namespace Signifyd\Connect\Model\Api\Core;

use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order as OrderResourceModel;
use Signifyd\Connect\Helper\ConfigHelper;
use Signifyd\Connect\Helper\OrderHelper;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\CasedataFactory;
use Signifyd\Connect\Model\ResourceModel\Casedata as CasedataResourceModel;
use Signifyd\Core\Api\CheckoutApiFactory;
use Signifyd\Core\Api\SaleApiFactory;
use Signifyd\Core\Api\WebhooksApiFactory;
use Signifyd\Core\Api\WebhooksV2ApiFactory;
use Signifyd\Connect\Model\Api\RecordReturnFactory;

class Client
{
    /**
     * @var ConfigHelper
     */
    public $configHelper;

    /**
     * @var Logger
     */
    public $logger;

    /**
     * @var OrderHelper
     */
    public $orderHelper;

    /**
     * @var JsonSerializer
     */
    public $jsonSerializer;

    /**
     * @var CasedataFactory
     */
    public $casedataFactory;

    /**
     * @var CasedataResourceModel
     */
    public $casedataResourceModel;

    /**
     * @var OrderResourceModel
     */
    public $orderResourceModel;

    /**
     * @var RecordReturnFactory
     */
    public $recordReturnFactory;

    /**
     * @var DirectoryList
     */
    public $directory;

    /**
     * @var SaleApiFactory
     */
    public $saleApiFactory;

    /**
     * @var CheckoutApiFactory
     */
    public $checkoutApiFactory;

    /**
     * @var WebhooksApiFactory
     */
    public $webhooksApiFactory;

    /**
     * @var WebhooksV2ApiFactory
     */
    public $webhooksV2ApiFactory;

    /**
     * Array of SignifydAPI, one for each store code
     *
     * @var array
     */
    public $signifydAPI = [];

    /**
     * @param ConfigHelper $configHelper
     * @param Logger $logger
     * @param OrderHelper $orderHelper
     * @param JsonSerializer $jsonSerializer
     * @param CasedataFactory $casedataFactory
     * @param CasedataResourceModel $casedataResourceModel
     * @param OrderResourceModel $orderResourceModel
     * @param RecordReturnFactory $recordReturnFactory
     * @param DirectoryList $directory
     * @param SaleApiFactory $saleApiFactory
     * @param CheckoutApiFactory $checkoutApiFactory
     * @param WebhooksApiFactory $webhooksApiFactory
     * @param WebhooksV2ApiFactory $webhooksV2ApiFactory
     */
    public function __construct(
        ConfigHelper $configHelper,
        Logger $logger,
        OrderHelper $orderHelper,
        JsonSerializer $jsonSerializer,
        CasedataFactory $casedataFactory,
        CasedataResourceModel $casedataResourceModel,
        OrderResourceModel $orderResourceModel,
        RecordReturnFactory $recordReturnFactory,
        DirectoryList $directory,
        SaleApiFactory $saleApiFactory,
        CheckoutApiFactory $checkoutApiFactory,
        WebhooksApiFactory $webhooksApiFactory,
        WebhooksV2ApiFactory $webhooksV2ApiFactory
    ) {
        $this->configHelper = $configHelper;
        $this->logger = $logger;
        $this->orderHelper = $orderHelper;
        $this->jsonSerializer = $jsonSerializer;
        $this->casedataFactory = $casedataFactory;
        $this->casedataResourceModel = $casedataResourceModel;
        $this->orderResourceModel = $orderResourceModel;
        $this->recordReturnFactory = $recordReturnFactory;
        $this->directory = $directory;
        $this->saleApiFactory = $saleApiFactory;
        $this->checkoutApiFactory = $checkoutApiFactory;
        $this->webhooksApiFactory = $webhooksApiFactory;
        $this->webhooksV2ApiFactory = $webhooksV2ApiFactory;
    }

    /**
     * @param $caseData
     * @param $order
     * @return false|\Signifyd\Core\Response\SaleResponse
     */
    public function postCaseToSignifyd($caseData, $order)
    {
        $this->logger->info(
            "Call sale api with request: " . $this->jsonSerializer->serialize($caseData),
            ['entity' => $order]
        );

        /** @var \Signifyd\Core\Response\SaleResponse $saleResponse */
        $saleResponse = $this->getSignifydSaleApi($order)->createOrder('orders/events/sales', $caseData);

        if (empty($saleResponse->getSignifydId()) === false) {
            $this->logger->info(
                "Sale api response: " . $this->jsonSerializer->serialize($saleResponse),
                ['entity' => $order]
            );
            $this->logger->debug("Case sent. Id is {$saleResponse->getSignifydId()}", ['entity' => $order]);
            $this->orderHelper->addCommentToStatusHistory(
                $order,
                "Signifyd: case created {$saleResponse->getSignifydId()}"
            );
            return $saleResponse;
        } else {
            $this->logger->error($this->jsonSerializer->serialize($saleResponse), ['entity' => $order]);
            $this->logger->error("Case failed to send.", ['entity' => $order]);
            $this->orderHelper->addCommentToStatusHistory($order, "Signifyd: failed to create case");

            return false;
        }
    }

    /**
     * @param $updateData
     * @param $order
     * @return bool|mixed|object|\Signifyd\Core\Response
     * @throws \Signifyd\Core\Exceptions\ApiException
     * @throws \Signifyd\Core\Exceptions\InvalidClassException
     */
    public function createReroute($updateData, $order)
    {
        $this->logger->info(
            "Call reroute api with request: " . $this->jsonSerializer->serialize($updateData),
            ['entity' => $order]
        );

        $caseResponse = $this->getSignifydSaleApi($order)->reroute($updateData);

        if (empty($caseResponse->getSignifydId()) === false) {
            $this->logger->info(
                "Reroute response: " . $this->jsonSerializer->serialize($caseResponse),
                ['entity' => $order]
            );
            $this->logger->debug("Reroute created. Id is {$caseResponse->getSignifydId()}", ['entity' => $order]);
            return $caseResponse;
        } else {
            $this->logger->error($this->jsonSerializer->serialize($caseResponse), ['entity' => $order]);
            $this->logger->error("Reroute failed to create.", ['entity' => $order]);
            return false;
        }
    }

    /**
     * @param Order $order
     * @return bool
     */
    public function cancelCaseOnSignifyd(Order $order)
    {
        $this->logger->debug("Trying to cancel case for order " . $order->getIncrementId(), ['entity' => $order]);

        /** @var \Signifyd\Connect\Model\Casedata $case */
        $case = $this->casedataFactory->create();
        $this->casedataResourceModel->load($case, $order->getId(), 'order_id');

        if ($case->isEmpty() || empty($case->getCode())) {
            $this->logger->debug(
                'Guarantee cancel skipped: case not found for order ' . $order->getIncrementId(),
                ['entity' => $order]
            );
            return false;
        }

        $guarantee = $case->getData('guarantee');

        if (empty($guarantee) || in_array($guarantee, ['DECLINED', 'REJECT', 'N/A'])) {
            $this->logger->debug("Guarantee cancel skipped: current guarantee is {$guarantee}", ['entity' => $order]);
            return false;
        }

        /** @var \Magento\Sales\Model\Order\Item $item */
        foreach ($order->getAllItems() as $item) {
            if ($item->getQtyToCancel() > 0 || $item->getQtyToRefund() > 0) {
                $message = 'Guarantee cancel skipped: order still have items not canceled or refunded';
                $this->logger->debug($message, ['entity' => $order]);
                return false;
            }
        }

        $this->logger->debug('Return case ' . $case->getData('order_id'), ['entity' => $order]);
        $recordReturn = $this->recordReturnFactory->create();
        $recordReturnResponse = $this->getSignifydSaleApi($order)->recordReturn($recordReturn($order));
        $returnId = $recordReturnResponse->getReturnId();

        if (isset($returnId)) {
            try {
                $case->setData('guarantee', 'CANCELED');
                $this->logger->debug("Return recorded with id {$returnId}", ['entity' => $order]);
                $this->orderHelper->addCommentToStatusHistory($order, "Signifyd: Return recorded with id {$returnId}");
                $isCaseLocked = $this->casedataResourceModel->isCaseLocked($case);

                // Some other process already locked the case, will not load or save
                if ($isCaseLocked === false) {
                    $this->casedataResourceModel->save($case);
                }

                return true;
            } catch (\Exception $e) {
                // Triggering case save to unlock case
                if ($case instanceof \Signifyd\Connect\Model\Casedata) {
                    $this->casedataResourceModel->save($case);
                }

                $this->logger->error('Failed to save case data to database: ' . $e->getMessage(), ['entity' => $order]);
                return false;
            }
        } else {
            $this->orderHelper->addCommentToStatusHistory($order, "Signifyd: failed to record a return");

            return false;
        }
    }

    /**
     * @param $caseData
     * @param $quote
     * @return bool|\Signifyd\Core\Response\CheckoutsResponse
     * @throws \Signifyd\Core\Exceptions\ApiException
     * @throws \Signifyd\Core\Exceptions\InvalidClassException
     * @throws \Signifyd\Core\Exceptions\LoggerException
     */
    public function postCaseFromQuoteToSignifyd($caseData, $quote)
    {
        $this->logger->info(
            "Call checkout api with request: " . $this->jsonSerializer->serialize($caseData),
            ['entity' => $quote]
        );

        $caseResponse = $this->getSignifydCheckoutApi($quote)
            ->createOrder('orders/events/checkouts', $caseData);

        if (empty($caseResponse->getSignifydId()) === false) {
            $this->logger->info(
                "Checkout api response: " . $this->jsonSerializer->serialize($caseResponse),
                ['entity' => $quote]
            );
            $this->logger->debug("Case sent. Id is {$caseResponse->getSignifydId()}", ['entity' => $quote]);
            return $caseResponse;
        } else {
            $this->logger->error($this->jsonSerializer->serialize($caseResponse), ['entity' => $quote]);
            $this->logger->error("Case failed to send.", ['entity' => $quote]);

            return false;
        }
    }

    /**
     * @param $transactionData
     * @param $entity
     * @return bool|mixed|object|\Signifyd\Core\Response
     * @throws \Signifyd\Core\Exceptions\ApiException
     * @throws \Signifyd\Core\Exceptions\InvalidClassException
     */
    public function postTransactionToSignifyd($transactionData, $entity)
    {
        $this->logger->info(
            "Call transaction api with request: " . $this->jsonSerializer->serialize($transactionData),
            ['entity' => $entity]
        );

        $caseResponse = $this->getSignifydCheckoutApi($entity)->createTransaction($transactionData);
        $tokenSent = $transactionData['checkoutId'];
        $tokenReceived = $caseResponse->getCheckoutId();

        if ($tokenSent === $tokenReceived) {
            $message = $entity instanceof \Magento\Quote\Model\Quote ?
                "Transaction sent to quote {$entity->getId()}. Token is {$caseResponse->getCheckoutId()}" :
                "Transaction sent to order {$entity->getIncrementId()}. Token is {$caseResponse->getCheckoutId()}";

            $this->logger->info(
                "Transaction api response: " . $this->jsonSerializer->serialize($transactionData),
                ['entity' => $entity]
            );
            $this->logger->debug($message, ['entity' => $entity]);
            return $caseResponse;
        } else {
            $this->logger->error($this->jsonSerializer->serialize($caseResponse), ['entity' => $entity]);
            $this->logger->error(
                "Transaction failed to send. Sent token ({$tokenSent}) is different from received ({$tokenReceived})",
                ['entity' => $entity]
            );
            return false;
        }
    }

    /**
     * @param \Magento\Framework\Model\AbstractModel|null $entity
     * @return \Signifyd\Core\Api\ApiModel
     */
    public function getSignifydSaleApi(\Magento\Framework\Model\AbstractModel $entity = null)
    {
        return $this->getSignifydApi('sale', $entity);
    }

    /**
     * @param \Magento\Framework\Model\AbstractModel|null $entity
     * @return \Signifyd\Core\Api\CheckoutApi
     */
    public function getSignifydCheckoutApi(\Magento\Framework\Model\AbstractModel $entity = null)
    {
        return $this->getSignifydApi('checkout', $entity);
    }

    /**
     * @param \Magento\Framework\Model\AbstractModel|null $entity
     * @return \Signifyd\Core\Api\WebhooksApi
     */
    public function getSignifydWebhookApi(\Magento\Framework\Model\AbstractModel $entity = null)
    {
        return $this->getSignifydApi('webhook', $entity);
    }

    /**
     * @param \Magento\Framework\Model\AbstractModel|null $entity
     * @return \Signifyd\Core\Api\WebhooksV2Api
     */
    public function getSignifydWebhookV2Api(\Magento\Framework\Model\AbstractModel $entity = null)
    {
        return $this->getSignifydApi('webhookV2', $entity);
    }

    /**
     * @param string $type
     * @param \Magento\Framework\Model\AbstractModel|null $entity
     * @return mixed
     */
    public function getSignifydApi($type, \Magento\Framework\Model\AbstractModel $entity = null)
    {
        $type = strtolower($type);
        $apiId = $type . $this->configHelper->getStoreCode($entity, true);

        if (isset($this->signifydAPI[$apiId]) === false ||
            is_object($this->signifydAPI[$apiId]) === false) {
            $apiKey = $this->configHelper->getConfigData('signifyd/general/key', $entity);
            $args = [
                'apiKey' => $apiKey,
                'logLocation' => $this->directory->getPath('log')
            ];

            switch ($type) {
                case 'sale':
                    $this->signifydAPI[$apiId] = $this->saleApiFactory->create(['args' => $args]);
                    break;

                case 'checkout':
                    $this->signifydAPI[$apiId] = $this->checkoutApiFactory->create(['args' => $args]);
                    break;

                case 'webhook':
                    $this->signifydAPI[$apiId] = $this->webhooksApiFactory->create(['args' => $args]);
                    break;

                case 'webhookv2':
                    $this->signifydAPI[$apiId] = $this->webhooksV2ApiFactory->create(['args' => $args]);
                    break;
            }
        }

        return $this->signifydAPI[$apiId];
    }
}
