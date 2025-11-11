<?php

namespace Signifyd\Connect\Plugin\Magento\Sales\Api;

use Magento\Sales\Api\Data\OrderSearchResultInterface;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Api\OrderRepositoryInterface as MagentoOrderRepositoryInterface;
use Magento\Sales\Api\Data\OrderExtensionFactory;
use Magento\Sales\Api\Data\OrderInterface;
use Signifyd\Connect\Api\CasedataRepositoryInterface;
use Signifyd\Connect\Model\CasedataFactory;

class OrderRepositoryInterface
{
    /**
     * @var CasedataRepositoryInterface
     */
    public $casedataRepository;

    /**
     * @var OrderFactory
     */
    public $orderFactory;

    /**
     * @var OrderExtensionFactory
     */
    public $orderExtensionFactory;

    /**
     * @var CasedataFactory
     */
    public $casedataFactory;

    /**
     * OrderRepositoryInterface construct.
     *
     * @param CasedataRepositoryInterface $casedataRepository
     * @param OrderExtensionFactory $extensionFactory
     * @param OrderFactory $orderFactory
     * @param CasedataFactory $casedataFactory
     */
    public function __construct(
        CasedataRepositoryInterface $casedataRepository,
        OrderExtensionFactory $extensionFactory,
        OrderFactory $orderFactory,
        CasedataFactory $casedataFactory
    ) {
        $this->casedataRepository = $casedataRepository;
        $this->orderExtensionFactory = $extensionFactory;
        $this->orderFactory = $orderFactory;
        $this->casedataFactory = $casedataFactory;
    }

    /**
     * Set signifyd order attribute data method.
     *
     * @param OrderInterface $order
     * @return void
     */
    public function setSignifydOrderAttributeData(OrderInterface $order)
    {
        try {
            /** @var \Signifyd\Connect\Model\Casedata $case */
            $case = $this->casedataRepository->getByOrderId($order->getId());

            if ($case->isEmpty()) {
                return;
            }

            $extensionAttributes = $order->getExtensionAttributes();
            $orderExtensionAttributes = $extensionAttributes ?? $this->orderExtensionFactory->create();

            if (empty($case->getData('guarantee')) === false) {
                $orderExtensionAttributes->setSignifydGuarantee($case->getData('guarantee'));
            }

            if (empty($case->getData('score')) === false) {
                $orderExtensionAttributes->setSignifydScore($case->getData('score'));
            }

            $order->setExtensionAttributes($orderExtensionAttributes);
        } catch (\Exception $e) {
            return;
        } catch (\Error $e) {
            return;
        }
    }

    /**
     * After get list method.
     *
     * @param MagentoOrderRepositoryInterface $subject
     * @param OrderSearchResultInterface $orderSearchResult
     * @return OrderSearchResultInterface
     */
    public function afterGetList(
        MagentoOrderRepositoryInterface $subject,
        OrderSearchResultInterface $orderSearchResult
    ) {
        foreach ($orderSearchResult->getItems() as $order) {
            $this->setSignifydOrderAttributeData($order);
        }

        return $orderSearchResult;
    }

    /**
     * After get method.
     *
     * @param MagentoOrderRepositoryInterface $subject
     * @param OrderInterface $resultOrder
     * @return OrderInterface
     */
    public function afterGet(
        MagentoOrderRepositoryInterface $subject,
        OrderInterface $resultOrder
    ) {
        $this->setSignifydOrderAttributeData($resultOrder);
        return $resultOrder;
    }
}
