<?php

namespace Signifyd\Connect\Model\Payment;

use Adyen\Config;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Signifyd\Connect\Api\PaymentVerificationInterface;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\PaymentGatewayFactory;
use Signifyd\Connect\Helper\ConfigHelper;

abstract class DataMapper implements PaymentVerificationInterface
{
    /**
     * @var Registry
     */
    protected $registry;

    /**
     * @var JsonSerializer
     */
    protected $jsonSerializer;

    /**
     * @var PaymentGatewayFactory
     */
    protected $paymentGatewayFactory;

    /**
     * List of payment methods codes
     * @var array
     */
    protected $allowedMethods = [];

    /**
     * Flag to prevent accidental loop for getCode/getData calls
     * @var bool
     */
    protected $getDataCalled = false;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var ConfigHelper
     */
    protected $configHelper;

    /**
     * DataMapper constructor.
     * @param Registry $registry
     * @param JsonSerializer $jsonSerializer
     * @param PaymentGatewayFactory $paymentGatewayFactory
     * @param Logger $logger
     * @param ConfigHelper $configHelper
     */
    public function __construct(
        Registry $registry,
        JsonSerializer $jsonSerializer,
        PaymentGatewayFactory $paymentGatewayFactory,
        Logger $logger,
        ConfigHelper $configHelper
    ) {
        $this->registry = $registry;
        $this->jsonSerializer = $jsonSerializer;
        $this->paymentGatewayFactory = $paymentGatewayFactory;
        $this->logger = $logger;
        $this->configHelper = $configHelper;
    }

    /**
     * @param null $key
     * @return bool|mixed
     */
    public function getSignifydPaymentData($key = null)
    {
        $paymentData = $this->registry->registry('signifyd_payment_data');

        if (empty($key)) {
            return $paymentData;
        }

        if (is_array($paymentData)) {
            if (isset($paymentData[$key])) {
                return $paymentData[$key];
            } elseif (isset($paymentData[strtolower($key)])) {
                return $paymentData[strtolower($key)];
            } elseif (isset($paymentData[strtoupper($key)])) {
                return $paymentData[strtoupper($key)];
            }
        }

        return false;
    }

    /**
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface $orderPayment
     * @return bool
     */
    public function checkMethod(\Magento\Sales\Api\Data\OrderPaymentInterface $orderPayment)
    {
        if (empty($this->allowedMethods) || in_array($orderPayment->getMethod(), $this->allowedMethods)) {
            return true;
        } else {
            throw new \InvalidArgumentException(
                'The "' . $orderPayment->getMethod() . '" it is not supported by ' . get_class($this) . ' mapper.'
            );
        }
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     * @return string
     */
    public function getCode(\Magento\Sales\Model\Order $order)
    {
        if ($this->getDataCalled) {
            return null;
        } else {
            return $this->getData($order);
        }
    }

    /**
     * This method must be called to retrieve data. Use getPaymentMethod to actual retrieve data from payment method\
     * on extending classes
     *
     * @param \Magento\Sales\Model\Order $order
     * @return string
     */
    public function getData(\Magento\Sales\Model\Order $order)
    {
        $gatewayIntegrationSettings = $this->getGatewayIntegrationSettings($order);
        $data = false;

        if (is_array($gatewayIntegrationSettings)) {
            $paymentGateway = $this->paymentGatewayFactory->get($gatewayIntegrationSettings);
            /** @var \Signifyd\Models\Payment\Response\ResponseInterface $response */
            $response = $paymentGateway->fetchData($this->getTransactionId($order), $order->getIncrementId());

            if ($response instanceof \Signifyd\Models\Payment\Response\ResponseInterface) {
                $data = $this->getPaymentDataFromGatewayResponse($response);

                $this->logger->info('Data found on payment gateway: ' .
                    (empty($data) ? 'false' : 'true'), ['entity' => $order]);
            }
        }

        if (empty($data)) {
            $this->checkMethod($order->getPayment());
            $data = $this->getPaymentData($order);
        }

        return $data;
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     * @return false|stdClass
     */
    public function getGatewayIntegrationSettings(\Magento\Sales\Model\Order $order)
    {
        $paymentMethod = $order->getPayment()->getMethod();
        $gatewayIntegrationSettings = $this->configHelper->getConfigData(
            "signifyd/gateway_integration/{$paymentMethod}",
            $order
        );

        if (empty($gatewayIntegrationSettings) === false) {
            try {
                /** @var stdClass $gatewayIntegrationSettings */
                $gatewayIntegrationSettings = $this->jsonSerializer->unserialize($gatewayIntegrationSettings);
                $this->logger->info($this->jsonSerializer->serialize($gatewayIntegrationSettings));
            } catch (\InvalidArgumentException $e) {
                $this->logger->error(
                    "Invalid gateway integration settings found to {$paymentMethod}: {$e->getMessage()}"
                );
                return false;
            }

            if (isset($gatewayIntegrationSettings['gateway']) === false) {
                $this->logger->error(
                    "Invalid gateway integration settings found to {$paymentMethod}: " .
                    "please provide a class name for the gateway on 'gateway' property"
                );
                return false;
            }

            if (class_exists($gatewayIntegrationSettings['gateway']) === false) {
                $this->logger->error(
                    "Invalid gateway integration settings found to {$paymentMethod}: " .
                    "gateway class {$gatewayIntegrationSettings['gateway']} does not exists"
                );
                return false;
            }

            if (isset($gatewayIntegrationSettings['params']) === false) {
                $this->logger->error(
                    "Invalid gateway integration settings found to {$paymentMethod}: " .
                    "please provide gateway params on 'params' property"
                );
                return false;
            }

            foreach ($gatewayIntegrationSettings['params'] as $key => $param) {
                if (isset($param['type']) === false) {
                    unset($gatewayIntegrationSettings['params'][$key]);
                    continue;
                }

                switch ($param['type']) {
                    case 'direct':
                        $gatewayIntegrationSettings['params'][$key] = $param['value'];
                        break;

                    case 'path':
                        $gatewayIntegrationSettings['params'][$key] = $this->configHelper->getConfigData(
                            $param['path'],
                            $order
                        );
                        break;
                }
            }

            return $gatewayIntegrationSettings;
        }

        return false;
    }

    /**
     * @param \Magento\Sales\Model\Order $order
     * @return string|string[]|null
     */
    public function getTransactionId(\Magento\Sales\Model\Order $order)
    {
        $transactionId = $order->getPayment()->getCcTransId();

        if (empty($transactionId)) {
            $transactionId = $order->getPayment()->getLastTransId();

            if (isset($transactionId) === false) {
                return null;
            }

            $transactionId = str_replace(['-capture', '-void', '-refund'], '', $transactionId);
        }

        return $transactionId;
    }
}
