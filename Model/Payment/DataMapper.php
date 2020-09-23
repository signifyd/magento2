<?php

namespace Signifyd\Connect\Model\Payment;

use Magento\Framework\Registry;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Signifyd\Connect\Api\PaymentVerificationInterface;
use Signifyd\Connect\Logger\Logger;
use Signifyd\Connect\Model\PaymentGatewayFactory;

abstract class DataMapper implements PaymentVerificationInterface
{
    /**
     * @var Registry
     */
    protected $registry;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

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
     * DataMapper constructor.
     * @param Registry $registry
     * @param ScopeConfigInterface $scopeConfig
     * @param JsonSerializer $jsonSerializer
     * @param PaymentGatewayFactory $paymentGatewayFactory
     * @param Logger $logger
     */
    public function __construct(
        Registry $registry,
        ScopeConfigInterface $scopeConfig,
        JsonSerializer $jsonSerializer,
        PaymentGatewayFactory $paymentGatewayFactory,
        Logger $logger
    ) {
        $this->registry = $registry;
        $this->scopeConfig = $scopeConfig;
        $this->jsonSerializer = $jsonSerializer;
        $this->paymentGatewayFactory = $paymentGatewayFactory;
        $this->logger = $logger;
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
            $response = $paymentGateway->fetchData($this->getTransactionId($order));

            if ($response instanceof \Signifyd\Models\Payment\Response\ResponseInterface) {
                $data = $this->getPaymentDataFromGatewayResponse($response);

                $this->logger->info('Data found on payment gateway: ' . (empty($data) ? 'false' : 'true'), $order);
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
        $gatewayIntegrationSettings = $this->scopeConfig->getValue(
            "signifyd/gateway_integration/{$paymentMethod}"
        );

        if (empty($gatewayIntegrationSettings) === false) {
            try {
                /** @var stdClass $gatewayIntegrationSettings */
                $gatewayIntegrationSettings = $this->jsonSerializer->unserialize($gatewayIntegrationSettings);
                $this->logger->info(serialize($gatewayIntegrationSettings));
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
                        $gatewayIntegrationSettings['params'][$key] = $this->scopeConfig->getValue($param['path']);
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
            $transactionId = str_replace(['-capture', '-void', '-refund'], '', $transactionId);
        }

        return $transactionId;
    }
}
