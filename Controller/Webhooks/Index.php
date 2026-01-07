<?php
/**
 * Copyright 2019 SIGNIFYD Inc. All rights reserved.
 * See LICENSE.txt for license details.
 */
namespace Signifyd\Connect\Controller\Webhooks;

use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Response\Http;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Model\ResourceModel\Order as OrderResourceModel;
use Magento\Store\Model\StoreManagerInterface;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Helper\ConfigHelper;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Filesystem\Driver\File;
use Signifyd\Connect\Model\Api\Core\Client;
use Signifyd\Connect\Model\Casedata;
use Signifyd\Connect\Model\Casedata\UpdateCaseV2Factory;
use Signifyd\Connect\Model\Casedata\UpdateCaseFactory;
use Signifyd\Connect\Model\CasedataFactory;
use Signifyd\Connect\Model\ResourceModel\Casedata as CasedataResourceModel;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Signifyd\Connect\Model\ResourceModel\Order as SignifydOrderResourceModel;
use Signifyd\Connect\Model\UpdateOrderFactory;
use Magento\Framework\Controller\ResultFactory;

/**
 * Controller action for handling webhook posts from Signifyd service
 */
class Index implements HttpPostActionInterface
{
    /**
     * @var Logger
     */
    public $logger;

    /**
     * @var ConfigHelper
     */
    public $configHelper;

    /**
     * @var File
     */
    public $file;

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
     * @var JsonSerializer
     */
    public $jsonSerializer;

    /**
     * @var StoreManagerInterface
     */
    public $storeManagerInterface;

    /**
     * @var OrderFactory
     */
    public $orderFactory;

    /**
     * @var SignifydOrderResourceModel
     */
    public $signifydOrderResourceModel;

    /**
     * @var UpdateCaseV2Factory
     */
    public $updateCaseV2Factory;

    /**
     * @var UpdateCaseFactory
     */
    public $updateCaseFactory;

    /**
     * @var UpdateOrderFactory
     */
    public $updateOrderFactory;

    /**
     * @var Client
     */
    public $client;

    /**
     * @var RequestInterface
     */
    public $request;

    /**
     * @var ResultFactory
     */
    public $resultFactory;

    /**
     * Index constructor.
     *
     * @param Context $context
     * @param Logger $logger
     * @param ConfigHelper $configHelper
     * @param FormKey $formKey
     * @param File $file
     * @param CasedataFactory $casedataFactory
     * @param CasedataResourceModel $casedataResourceModel
     * @param OrderResourceModel $orderResourceModel
     * @param JsonSerializer $jsonSerializer
     * @param OrderFactory $orderFactory
     * @param SignifydOrderResourceModel $signifydOrderResourceModel
     * @param UpdateCaseV2Factory $updateCaseV2Factory
     * @param UpdateCaseFactory $updateCaseFactory
     * @param UpdateOrderFactory $updateOrderFactory
     * @param StoreManagerInterface $storeManagerInterface
     * @param Client $client
     * @param RequestInterface $request
     * @param ResultFactory $resultFactory
     * @throws LocalizedException
     */
    public function __construct(
        Context $context,
        Logger $logger,
        ConfigHelper $configHelper,
        FormKey $formKey,
        File $file,
        CasedataFactory $casedataFactory,
        CasedataResourceModel $casedataResourceModel,
        OrderResourceModel $orderResourceModel,
        JsonSerializer $jsonSerializer,
        OrderFactory $orderFactory,
        SignifydOrderResourceModel $signifydOrderResourceModel,
        UpdateCaseV2Factory $updateCaseV2Factory,
        UpdateCaseFactory $updateCaseFactory,
        UpdateOrderFactory $updateOrderFactory,
        StoreManagerInterface $storeManagerInterface,
        Client $client,
        RequestInterface $request,
        ResultFactory $resultFactory
    ) {
        $this->logger = $logger;
        $this->configHelper = $configHelper;
        $this->file = $file;
        $this->casedataFactory = $casedataFactory;
        $this->casedataResourceModel = $casedataResourceModel;
        $this->orderResourceModel = $orderResourceModel;
        $this->jsonSerializer = $jsonSerializer;
        $this->storeManagerInterface = $storeManagerInterface;
        $this->orderFactory = $orderFactory;
        $this->signifydOrderResourceModel = $signifydOrderResourceModel;
        $this->updateCaseV2Factory = $updateCaseV2Factory;
        $this->updateCaseFactory = $updateCaseFactory;
        $this->updateOrderFactory = $updateOrderFactory;
        $this->client = $client;
        $this->request = $request;
        $this->resultFactory = $resultFactory;

        // Compatibility with Magento 2.3+ which required form_key on every request
        // Magento expects class to implement \Magento\Framework\App\CsrfAwareActionInterface but this causes
        // a backward incompatibility to Magento versions below 2.3
        if (interface_exists(\Magento\Framework\App\CsrfAwareActionInterface::class)) {
            if ($this->request->isPost() && empty($this->request->getParam('form_key'))) {
                $this->request->setParam('form_key', $formKey->getFormKey());
            }
        }
    }

    /**
     * Get raw post method.
     *
     * @return string
     */
    public function getRawPost()
    {
        $post = $this->file->fileGetContents("php://input");

        if ($post) {
            return $post;
        }

        return '';
    }

    /**
     * Execute method.
     *
     * @return Json|ResultInterface
     * @throws LocalizedException|AlreadyExistsException|FileSystemException
     */
    public function execute(): Json|ResultInterface
    {
        $request = $this->getRawPost();

        if (empty($this->request->getHeader('signifyd-checkpoint')) === false) {
            $hash = $this->request->getHeader('signifyd-sec-hmac-sha256');
            $topic = $this->request->getHeader('signifyd-topic');
        } else {
            $hash = $this->request->getHeader('X-SIGNIFYD-SEC-HMAC-SHA256');
            $topic = $this->request->getHeader('X-SIGNIFYD-TOPIC');
        }

        $this->logger->debug('WEBHOOK: request: ' . $request);
        $this->logger->debug('WEBHOOK: request hash: ' . $hash);
        $this->logger->debug('WEBHOOK: request topic: ' . $topic);

        return $this->processRequest($request, $hash, $topic);
    }

    /**
     * Process request method.
     *
     * @param mixed $request
     * @param ?string $hash
     * @param ?string $topic
     * @return Json|ResultInterface
     * @throws AlreadyExistsException|FileSystemException|LocalizedException
     */
    public function processRequest(mixed $request, ?string $hash, ?string $topic): Json|ResultInterface
    {
        /** @var Json $result */
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);

        if (empty($hash) || empty($request)) {
            $result->setData(['message' =>"You have successfully reached the webhook endpoint"]);
            $result->setHttpResponseCode(Http::STATUS_CODE_200);
            return $result;
        }

        try {
            $requestJson = (object) $this->jsonSerializer->unserialize($request);
        } catch (\InvalidArgumentException $e) {
            $message = 'Invalid JSON provided on request body';
            $result->setData(['message' =>$message]);
            $this->logger->debug("WEBHOOK: {$message}");
            $result->setHttpResponseCode(Http::STATUS_CODE_400);
            return $result;
        }

        if (isset($requestJson->caseId)) {
            $caseId = $requestJson->caseId;
            $webHookVersion = "v2";
        } elseif (isset($requestJson->signifydId)) {
            $caseId = $requestJson->signifydId;
            $webHookVersion = "v3";
        } else {
            $result->setData(['message' =>"Invalid body, no 'caseId' field found on request"]);
            $result->setHttpResponseCode(Http::STATUS_CODE_500);
            return $result;
        }

        switch ($topic) {
            case 'cases/test':
                // Test is only verifying that the endpoint is reachable. So we just complete here
                $result->setHttpResponseCode(Http::STATUS_CODE_200);
                return $result;

            case 'cases/creation':
                if ($this->configHelper->isScoreOnly() === false) {
                    $message = 'Case creation will not be processed by Magento';
                    $result->setData(['message' =>$message]);
                    $this->logger->debug("WEBHOOK: {$message}");
                    $result->setHttpResponseCode(Http::STATUS_CODE_200);
                    return $result;
                }
                break;
        }

        /** @var \Signifyd\Connect\Model\Casedata $case */
        $case = $this->casedataFactory->create();

        try {
            $httpCode = null;

            try {
                $this->casedataResourceModel->loadForUpdate($case, (string) $caseId, 'code');
            } catch (\Exception $e) {
                $result->setData(['message' => __($e->getMessage())]);
                $result->setHttpResponseCode(Http::STATUS_CODE_423);
                return $result;
            }

            if ($case->isEmpty()) {
                $result->setData(['message' => __("Case {$caseId} on request not found on Magento")]);
                $result->setHttpResponseCode(Http::STATUS_CODE_400);
                return $result;
            }

            if ($case->getEntries('processed_by_gateway') === false) {
                $this->casedataResourceModel->save($case);
                $result->setData(['message' => __("Case {$caseId} awaiting gateway processing")]);
                $result->setHttpResponseCode(Http::STATUS_CODE_400);
                return $result;
            }

            $signifydWebhookApi = $this->client->getSignifydWebhookApi($case);

            if ($signifydWebhookApi->validWebhookRequest($request, $hash, $topic) == false) {
                $result->setData(['message' => __("Invalid webhook request")]);
                $result->setHttpResponseCode(Http::STATUS_CODE_403);
                return $result;
            } elseif ($this->configHelper->isEnabled($case) == false) {
                $result->setData(['message' => __("Signifyd plugin it is not enabled")]);
                $result->setHttpResponseCode(Http::STATUS_CODE_400);
                return $result;
            } elseif ($case->getMagentoStatus() == Casedata::WAITING_SUBMISSION_STATUS ||
                $case->getMagentoStatus() == Casedata::AWAITING_PSP
            ) {
                $result->setData(['message' => __("Case {$caseId} it is not ready to be updated")]);
                $result->setHttpResponseCode(Http::STATUS_CODE_400);
                return $result;
            } elseif ($case->getPolicyName() === Casedata::PRE_AUTH &&
                $case->getGuarantee() !== 'HOLD' &&
                $case->getGuarantee() !== 'PENDING'
            ) {
                $result->setData([
                    'message' =>
                        __("Case {$caseId} already completed by synchronous response, no action will be taken")
                ]);
                $result->setHttpResponseCode(Http::STATUS_CODE_200);
                return $result;
            }

            $order = $this->orderFactory->create();
            $this->signifydOrderResourceModel->load($order, $case->getData('order_id'));

            if ($order->isEmpty()) {
                $result->setData(['message' => __("Order not found")]);
                $result->setHttpResponseCode(Http::STATUS_CODE_400);
                return $result;
            }

            if ($this->configHelper->processMerchantReview($order) === false &&
                isset($requestJson->decision)
            ) {
                if (is_array($requestJson->decision)) {
                    $checkpointActionReason = $requestJson->decision['checkpointActionReason'] ?? null;
                } else {
                    $checkpointActionReason = $requestJson->decision->checkpointActionReason ?? null;
                }

                if ($checkpointActionReason == 'MERCHANT_REVIEW') {
                    $this->logger->info(
                        "WEBHOOK: Case {$case->getId()} will not be processed because the ".
                        "request was made via 'Order review flag' in the Signifyd dashboard",
                        ['entity' => $case]
                    );

                    $result->setData(['message' => __("Request made via 'Order review flag'")]);
                    $result->setHttpResponseCode(Http::STATUS_CODE_200);
                    return $result;
                }
            }

            $this->logger->info(
                "WEBHOOK: Processing case {$case->getId()} with request {$request} ",
                ['entity' => $case]
            );
            $this->storeManagerInterface->setCurrentStore($order->getStore()->getStoreId());
            $currentCaseHash = sha1(implode(',', $case->getData()));

            switch ($webHookVersion) {
                case "v2":
                    $updateCaseV2 = $this->updateCaseV2Factory->create();
                    $case = $updateCaseV2($case, $requestJson);
                    break;
                case "v3":
                    $updateCase = $this->updateCaseFactory->create();
                    $case = $updateCase($case, $requestJson);
                    break;
            }

            $newCaseHash = sha1(implode(',', $case->getData()));

            if ($currentCaseHash == $newCaseHash) {
                $this->logger->debug(
                    "Case {$caseId} already update with this data, no action will be taken",
                    ['entity' => $case]
                );

                $result->setData([
                    'message' =>
                        __("Case {$caseId} already update with this data, no action will be taken")
                ]);
                $result->setHttpResponseCode(Http::STATUS_CODE_200);
                return $result;
            }

            $updateOrder = $this->updateOrderFactory->create();
            $case = $updateOrder($case);

            $this->casedataResourceModel->save($case);

            $result->setHttpResponseCode(Http::STATUS_CODE_200);
            return $result;
        } catch (\Exception $e) {
            $context = [];

            // Triggering case save to unlock case
            if ($case instanceof \Signifyd\Connect\Model\ResourceModel\Casedata) {
                $this->casedataResourceModel->save($case);
                $context['entity'] = $case;
            }

            $httpCode = empty($httpCode) ? 403 : $httpCode;
            $result->setHttpResponseCode($httpCode);
            $result->setData(['message' =>$e->getMessage()]);
            $this->logger->error("WEBHOOK: {$e->getMessage()}", $context);
            return $result;
        } catch (\Error $e) {
            $context = [];

            // Triggering case save to unlock case
            if ($case instanceof \Signifyd\Connect\Model\ResourceModel\Casedata) {
                $this->casedataResourceModel->save($case);
                $context['entity'] = $case;
            }

            $httpCode = empty($httpCode) ? 403 : $httpCode;
            $result->setHttpResponseCode($httpCode);
            $result->setData(['message' =>$e->getMessage()]);
            $this->logger->error("WEBHOOK: {$e->getMessage()}", $context);
            return $result;
        }
    }
}
