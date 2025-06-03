<?php

namespace Signifyd\Connect\Model\Payment;

use Adyen\Config;
use Magento\Framework\ObjectManagerInterface;
use Signifyd\Connect\Model\Registry;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Signifyd\Connect\Api\PaymentVerificationInterface;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\PaymentGatewayFactory;
use Signifyd\Connect\Helper\ConfigHelper;
use Magento\Framework\Encryption\EncryptorInterface;

abstract class DataMapper implements PaymentVerificationInterface
{
    /**
     * @var Registry
     */
    public $registry;

    /**
     * @var JsonSerializer
     */
    public $jsonSerializer;

    /**
     * @var PaymentGatewayFactory
     */
    public $paymentGatewayFactory;

    /**
     * List of payment methods codes
     * @var array
     */
    public $allowedMethods = [];

    /**
     * Flag to prevent accidental loop for getCode/getData calls
     * @var bool
     */
    public $getDataCalled = false;

    /**
     * @var Logger
     */
    public $logger;

    /**
     * @var ConfigHelper
     */
    public $configHelper;

    /**
     * @var ObjectManagerInterface
     */
    public $objectManagerInterface;

    /**
     * @var EncryptorInterface
     */
    public $encryptor;

    /**
     * DataMapper constructor.
     *
     * @param Registry $registry
     * @param JsonSerializer $jsonSerializer
     * @param PaymentGatewayFactory $paymentGatewayFactory
     * @param Logger $logger
     * @param ConfigHelper $configHelper
     * @param ObjectManagerInterface $objectManagerInterface
     * @param EncryptorInterface $encryptor
     */
    public function __construct(
        Registry $registry,
        JsonSerializer $jsonSerializer,
        PaymentGatewayFactory $paymentGatewayFactory,
        Logger $logger,
        ConfigHelper $configHelper,
        ObjectManagerInterface $objectManagerInterface,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor
    ) {
        $this->registry = $registry;
        $this->jsonSerializer = $jsonSerializer;
        $this->paymentGatewayFactory = $paymentGatewayFactory;
        $this->logger = $logger;
        $this->configHelper = $configHelper;
        $this->objectManagerInterface = $objectManagerInterface;
        $this->encryptor = $encryptor;
    }

    /**
     * Get signifyd payment data method.
     *
     * @param mixed $key
     * @return bool|mixed
     */
    public function getSignifydPaymentData($key = null)
    {
        $paymentData = $this->registry->getData('signifyd_payment_data');

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
     * Check method method.
     *
     * @param \Magento\Sales\Api\Data\OrderPaymentInterface $orderPayment
     * @return bool
     */
    public function checkMethod(\Magento\Sales\Api\Data\OrderPaymentInterface $orderPayment)
    {
        if (empty($this->allowedMethods) || in_array($orderPayment->getMethod(), $this->allowedMethods)) {
            return true;
        } else {
            throw new \InvalidArgumentException(
                'The "' . $orderPayment->getMethod() . '" it is not supported by ' . get_class($this) .
                ' mapper.'
            );
        }
    }

    /**
     * Get code method.
     *
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
     *
     * On extending classes
     *
     * @param \Magento\Sales\Model\Order $order
     * @return mixed
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
     * Get gateway integration settings method.
     *
     * @param \Magento\Sales\Model\Order $order
     * @return array|bool|float|int|mixed|string
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
                    case 'path_secure':
                        $gatewayIntegrationSettings['params'][$key] = $this->encryptor->decrypt(
                            $this->configHelper->getConfigData(
                                $param['path'],
                                $order
                            )
                        );
                        break;
                }
            }

            return $gatewayIntegrationSettings;
        }

        return false;
    }

    /**
     * Get transaction id method.
     *
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
