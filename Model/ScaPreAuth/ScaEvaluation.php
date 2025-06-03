<?php
namespace Signifyd\Connect\Model\ScaPreAuth;

use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Quote\Model\Quote;
use Signifyd\Connect\Helper\ConfigHelper;
use Signifyd\Connect\Model\CasedataFactory;
use Signifyd\Connect\Model\ResourceModel\Casedata as CasedataResourceModel;
use Signifyd\Models\ScaEvaluationFactory as ScaEvaluationModelFactory;

class ScaEvaluation extends AbstractModel
{
    /**
     * @var CasedataFactory
     */
    public $casedataFactory;

    /**
     * @var CasedataResourceModel
     */
    public $casedataResourceModel;

    /**
     * @var ScaEvaluationModelFactory
     */
    public $scaEvaluationModelFactory;

    /**
     * @var SerializerInterface
     */
    public $serializer;

    /**
     * @var ConfigHelper
     */
    public $configHelper;

    /**
     * ScaEvaluation method.
     *
     * @param Context $context
     * @param Registry $registry
     * @param CasedataFactory $casedataFactory
     * @param CasedataResourceModel $casedataResourceModel
     * @param ScaEvaluationModelFactory $scaEvaluationModelFactory
     * @param SerializerInterface $serializer
     * @param ConfigHelper $configHelper
     */
    public function __construct(
        Context $context,
        Registry $registry,
        CasedataFactory $casedataFactory,
        CasedataResourceModel $casedataResourceModel,
        ScaEvaluationModelFactory $scaEvaluationModelFactory,
        SerializerInterface $serializer,
        ConfigHelper $configHelper
    ) {
        parent::__construct($context, $registry);
        $this->casedataFactory = $casedataFactory;
        $this->casedataResourceModel = $casedataResourceModel;
        $this->scaEvaluationModelFactory = $scaEvaluationModelFactory;
        $this->serializer = $serializer;
        $this->configHelper = $configHelper;
    }

    /**
     * Get sca evaluation method.
     *
     * @param Quote $quote
     * @return false|\Signifyd\Models\ScaEvaluation
     */
    public function getScaEvaluation($quote)
    {
        $quoteId = $quote->getId();

        if ($this->configHelper->isEnabled($quote) == false) {
            return false;
        }

        /** @var \Signifyd\Connect\Model\Casedata $case */
        $case = $this->casedataFactory->create();
        $this->casedataResourceModel->load($case, $quoteId, 'quote_id');

        if ($case->isEmpty()) {
            return false;
        }

        if ($this->getIsSoftDecline() === true &&
            $case->getGuarantee() == "ACCEPT") {
            /** @var \Signifyd\Models\ScaEvaluation $scaEvaluation */
            $scaEvaluation = $this->scaEvaluationModelFactory->create();
            $scaEvaluation->outcome = 'SOFT_DECLINE';

            return $scaEvaluation;
        }

        $preAuthSca = $case->getEntries('sca_pre_auth');

        if (isset($preAuthSca)) {
            $arrayPreAuthSca = $this->serializer->unserialize($preAuthSca);

            /** @var \Signifyd\Models\ScaEvaluation $scaEvaluation */
            $scaEvaluation = $this->scaEvaluationModelFactory->create(['data' => $arrayPreAuthSca]);
            return $scaEvaluation;
        }

        return false;
    }
}
