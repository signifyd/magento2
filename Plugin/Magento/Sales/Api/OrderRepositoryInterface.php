<?php

namespace Signifyd\Connect\Plugin\Magento\Sales\Api;

use Magento\Sales\Api\Data\OrderSearchResultInterface;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Api\OrderRepositoryInterface as MagentoOrderRepositoryInterface;
use Magento\Sales\Api\Data\OrderExtensionFactory;
use Magento\Sales\Api\Data\OrderInterface;
use Signifyd\Connect\Model\CasedataFactory;
use Signifyd\Connect\Model\ResourceModel\Casedata as CasedataResourceModel;

class OrderRepositoryInterface
{
    /**
     * @var OrderFactory
     */
    protected $orderFactory;

    /**
     * @var OrderExtensionFactory
     */
    protected $orderExtensionFactory;

    /**
     * @var CasedataResourceModel
     */
    protected $casedataResourceModel;

    /**
     * @var CasedataFactory
     */
    protected $casedataFactory;

    /**
     * @param OrderExtensionFactory $extensionFactory
     * @param OrderFactory $orderFactory
     * @param CasedataResourceModel $casedataResourceModel
     * @param CasedataFactory $casedataFactory
     */
    public function __construct(
        OrderExtensionFactory $extensionFactory,
        OrderFactory $orderFactory,
        CasedataResourceModel $casedataResourceModel,
        CasedataFactory $casedataFactory
    ) {
        $this->orderExtensionFactory = $extensionFactory;
        $this->orderFactory = $orderFactory;
        $this->casedataResourceModel = $casedataResourceModel;
        $this->casedataFactory = $casedataFactory;
    }

    /**
     * @param OrderInterface $order
     * @return void
     */
    public function setSignifydOrderAttributeData(OrderInterface $order)
    {
        try {
            /** @var \Signifyd\Connect\Model\Casedata $case */
            $case = $this->casedataFactory->create();
            $this->casedataResourceModel->load($case, $order->getId(), 'order_id');

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
