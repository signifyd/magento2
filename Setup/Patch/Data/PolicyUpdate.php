<?php

namespace Signifyd\Connect\Setup\Patch\Data;

use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Config\Model\ResourceModel\Config\Data\CollectionFactory as ConfigDataCollectionFactory;

class PolicyUpdate implements DataPatchInterface
{
    /**
     * @var WriterInterface
     */
    protected $configWriter;

    /**
     * @var ConfigDataCollectionFactory
     */
    protected $configDataCollectionFactory;

    /**
     * @var JsonSerializer
     */
    protected $jsonSerializer;

    /**
     * @param WriterInterface $configWriter
     * @param ConfigDataCollectionFactory $configDataCollectionFactory
     * @param JsonSerializer $jsonSerializer
     */
    public function __construct(
        WriterInterface $configWriter,
        ConfigDataCollectionFactory $configDataCollectionFactory,
        JsonSerializer $jsonSerializer
    ) {
        $this->configWriter = $configWriter;
        $this->configDataCollectionFactory = $configDataCollectionFactory;
        $this->jsonSerializer = $jsonSerializer;
    }

    public function apply()
    {
        $configDataCollection = $this->configDataCollectionFactory->create();

        $configDataCollection
            ->addFieldToFilter('path', ['eq' => 'signifyd/advanced/policy_name']);

        foreach ($configDataCollection as $configData) {
            $scope = $configData->getData('scope');
            $scopeId = $configData->getData('scope_id');
            $value = $configData->getData('value');

            try {
                $configPolicy = $this->jsonSerializer->unserialize($value);
            } catch (\InvalidArgumentException $e) {
                if ($value === 'PRE_AUTH' || $value === 'POST_AUTH') {
                    $this->configWriter->save('signifyd/general/policy_name', $value, $scope, $scopeId);
                }

                $this->configWriter->save(
                    'signifyd/general/policy_exceptions',
                    '[]',
                    $scope,
                    $scopeId
                );

                continue;
            }

            $policyExceptions = [];
            $index = 0;

            foreach ($configPolicy as $poilicy => $paymentMethodsArray) {
                if ($poilicy == 'PRE_AUTH' || $poilicy == 'SCA_PRE_AUTH' || $poilicy == 'POST_AUTH') {
                    if (is_array($paymentMethodsArray) === false) {
                        continue;
                    }

                    foreach ($paymentMethodsArray as $key => $paymentMethods) {
                        $policyExceptions["mapping" . $index] = [
                            'policy' => $poilicy,
                            'payment_method' => $paymentMethods
                        ];
                        $index += 1;
                    }
                }
            }

            $this->configWriter->save(
                'signifyd/general/policy_exceptions',
                $this->jsonSerializer->serialize($policyExceptions),
                $scope,
                $scopeId
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getAliases()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public static function getDependencies()
    {
        return [];
    }
}