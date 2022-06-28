<?php
namespace Signifyd\Connect\Model\TRAPreAuth;

use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\SerializerInterface;
use Signifyd\Connect\Model\CasedataFactory;
use Signifyd\Connect\Model\ResourceModel\Casedata as CasedataResourceModel;
use Signifyd\Models\ScaEvaluationFactory as ScaEvaluationModelFactory;

class ScaEvaluation extends AbstractModel
{
    /**
     * @var CasedataFactory
     */
    protected $casedataFactory;

    /**
     * @var CasedataResourceModel
     */
    protected $casedataResourceModel;

    /**
     * @var ScaEvaluationModelFactory
     */
    protected $scaEvaluationModelFactory;

    /**
     * @var SerializerInterface
     */
    protected $serializer;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param CasedataFactory $casedataFactory
     * @param CasedataResourceModel $casedataResourceModel
     * @param ScaEvaluationModelFactory $scaEvaluationModelFactory
     * @param SerializerInterface $serializer
     */
    public function __construct(
        Context $context,
        Registry $registry,
        CasedataFactory $casedataFactory,
        CasedataResourceModel $casedataResourceModel,
        ScaEvaluationModelFactory $scaEvaluationModelFactory,
        SerializerInterface $serializer
    ) {
        parent::__construct($context, $registry);
        $this->casedataFactory = $casedataFactory;
        $this->casedataResourceModel = $casedataResourceModel;
        $this->scaEvaluationModelFactory = $scaEvaluationModelFactory;
        $this->serializer = $serializer;
    }

    public function getScaEvaluation($quoteId)
    {
        /** @var \Signifyd\Connect\Model\Casedata $case */
        $case = $this->casedataFactory->create();
        $this->casedataResourceModel->load($case, $quoteId, 'quote_id');

        if ($case->isEmpty() === false) {
            $preAuthTra = $case->getEntries('tra_pre_auth');

            if (isset($preAuthTra)) {
                $arrayPreAuthTra = $this->serializer->unserialize($preAuthTra);

                /** @var \Signifyd\Models\ScaEvaluation $scaEvaluation */
                $scaEvaluation = $this->scaEvaluationModelFactory->create(['data' => $arrayPreAuthTra]);
                return $scaEvaluation;
            }
        }

        return false;
    }
}
