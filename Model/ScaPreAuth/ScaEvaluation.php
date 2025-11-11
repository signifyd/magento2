<?php
namespace Signifyd\Connect\Model\ScaPreAuth;

use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Quote\Model\Quote;
use Signifyd\Connect\Api\CasedataRepositoryInterface;
use Signifyd\Connect\Helper\ConfigHelper;
use Signifyd\Connect\Model\CasedataFactory;
use Signifyd\Models\ScaEvaluationFactory as ScaEvaluationModelFactory;

class ScaEvaluation extends AbstractModel
{
    /**
     * @var CasedataRepositoryInterface
     */
    public $casedataRepository;

    /**
     * @var CasedataFactory
     */
    public $casedataFactory;

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
     * @param CasedataRepositoryInterface $casedataRepository
     * @param Context $context
     * @param Registry $registry
     * @param CasedataFactory $casedataFactory
     * @param ScaEvaluationModelFactory $scaEvaluationModelFactory
     * @param SerializerInterface $serializer
     * @param ConfigHelper $configHelper
     */
    public function __construct(
        CasedataRepositoryInterface $casedataRepository,
        Context $context,
        Registry $registry,
        CasedataFactory $casedataFactory,
        ScaEvaluationModelFactory $scaEvaluationModelFactory,
        SerializerInterface $serializer,
        ConfigHelper $configHelper
    ) {
        parent::__construct($context, $registry);
        $this->casedataRepository = $casedataRepository;
        $this->casedataFactory = $casedataFactory;
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
        if ($this->configHelper->isEnabled($quote) == false) {
            return false;
        }

        /** @var \Signifyd\Connect\Model\Casedata $case */
        $case = $this->casedataRepository->getByQuoteId($quote->getId());

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
