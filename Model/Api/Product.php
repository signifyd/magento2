<?php

namespace Signifyd\Connect\Model\Api;

use Magento\Catalog\Model\CategoryFactory;
use Magento\Catalog\Model\ResourceModel\Category as CategoryResourceModel;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Sales\Model\Order\Item as OrderItem;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use Magento\Store\Model\StoreManagerInterface;

class Product
{
    /**
     * @var CategoryCollectionFactory
     */
    public $categoryCollectionFactory;

    /**
     * @var CategoryFactory
     */
    public $categoryFactory;

    /**
     * @var CategoryResourceModel
     */
    public $categoryResourceModel;

    /**
     * @var StoreManagerInterface
     */
    public $storeManagerInterface;

    /**
     * @var SubscriptionFactory
     */
    public $subscriptionFactory;

    /**
     * @var GiftMessageFactory
     */
    public $giftMessageFactory;

    /**
     * @var RegistryIdFactory
     */
    public $registryIdFactory;

    /**
     * Product construct.
     *
     * @param CategoryCollectionFactory $categoryCollectionFactory
     * @param CategoryFactory $categoryFactory
     * @param CategoryResourceModel $categoryResourceModel
     * @param StoreManagerInterface $storeManagerInterface
     * @param SubscriptionFactory $subscriptionFactory
     * @param GiftMessageFactory $giftMessageFactory
     * @param RegistryIdFactory $registryIdFactory
     */
    public function __construct(
        CategoryCollectionFactory $categoryCollectionFactory,
        CategoryFactory $categoryFactory,
        CategoryResourceModel $categoryResourceModel,
        StoreManagerInterface $storeManagerInterface,
        SubscriptionFactory $subscriptionFactory,
        GiftMessageFactory $giftMessageFactory,
        RegistryIdFactory $registryIdFactory
    ) {
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->categoryFactory = $categoryFactory;
        $this->categoryResourceModel = $categoryResourceModel;
        $this->storeManagerInterface = $storeManagerInterface;
        $this->subscriptionFactory = $subscriptionFactory;
        $this->giftMessageFactory = $giftMessageFactory;
        $this->registryIdFactory = $registryIdFactory;
    }

    /**
     * Construct a new Product object
     *
     * @param OrderItem|QuoteItem $entity
     * @return array
     */
    public function __invoke($entity)
    {
        if ($entity instanceof OrderItem) {
            $product = $this->makeProductFromOrder($entity);
        } elseif ($entity instanceof QuoteItem) {
            $product = $this->makeProductFromQuote($entity);
        } else {
            $product = [];
        }

        return $product;
    }

    /**
     * Make product from order method.
     *
     * @param OrderItem $item
     * @return array
     */
    protected function makeProductFromOrder(OrderItem $item)
    {
        $product = $item->getProduct();
        $productImageUrl = $this->getProductImage($product);

        $productCategorysId = $product->getCategoryIds();
        $categoryCollection = $this->categoryCollectionFactory->create()
            ->addFieldToFilter('entity_id', ['in' => $productCategorysId])
            ->addFieldToFilter('level', ['neq' => 0])
            ->setOrder('position', 'ASC')
            ->setOrder('level', 'ASC');
        $productCategoryId = null;
        $productSubCategoryId = null;

        /** @var \Magento\Catalog\Model\Category $category */
        foreach ($categoryCollection as $category) {
            if (isset($productCategoryId) && isset($productSubCategoryId)) {
                break;
            }

            switch ($category->getLevel()) {
                case 2:
                    $productCategoryId = $category->getId();
                    break;
                case 3:
                    $productSubCategoryId = $category->getId();
                    break;
            }
        }

        if (isset($productCategoryId)) {
            /** @var \Magento\Catalog\Model\Category $mainCategory */
            $mainCategory = $this->categoryFactory->create();
            $this->categoryResourceModel->load($mainCategory, $productCategoryId);
            $mainCategoryName = $mainCategory->getName();
        } else {
            $mainCategoryName = null;
        }

        if (isset($productSubCategoryId)) {
            /** @var \Magento\Catalog\Model\Category $subCategory */
            $subCategory = $this->categoryFactory->create();
            $this->categoryResourceModel->load($subCategory, $productSubCategoryId);
            $subCategoryName = $subCategory->getName();
        } else {
            $subCategoryName = null;
        }

        $itemPriceInclTax = $item->getPriceInclTax() ?? 0;

        $itemPrice = floatval(number_format($itemPriceInclTax, 2, '.', ''));

        if ($itemPrice <= 0) {
            if ($item->getParentItem()) {
                $type = $item->getParentItem()->getProductType();
                if ($type === 'configurable' || $type === 'bundle') {
                    $parentItemPriceInclTax = $item->getParentItem()->getPriceInclTax() ?? 0;

                    $itemPrice = floatval(number_format($parentItemPriceInclTax, 2, '.', ''));
                }
            }
        }

        $product = [];
        $product['itemName'] = $item->getName();
        $product['itemPrice'] = $itemPrice;
        $product['itemQuantity'] = (int)$item->getQtyOrdered();
        $product['itemIsDigital'] = (bool) $item->getIsVirtual();
        $product['itemCategory'] = $mainCategoryName;
        $product['itemSubCategory'] = $subCategoryName;
        $product['itemId'] = $item->getSku();
        $product['itemImage'] = $productImageUrl;
        $product['itemUrl'] = $item->getProduct()->getProductUrl();
        $product['itemWeight'] = $item->getProduct()->getWeight();
        $product['shipmentId'] = null;
        $product['subscription'] = ($this->subscriptionFactory->create())();
        $product['giftMessage'] = ($this->giftMessageFactory->create())();
        $product['registryId'] = ($this->registryIdFactory->create())();

        return $product;
    }

    /**
     * Make product from quote method.
     *
     * @param QuoteItem $item
     * @return array
     */
    protected function makeProductFromQuote(QuoteItem $item)
    {
        $product = $item->getProduct();
        $productImageUrl = $this->getProductImage($product);

        $productCategorysId = $product->getCategoryIds();
        $categoryCollection = $this->categoryCollectionFactory->create()
            ->addFieldToFilter('entity_id', ['in' => $productCategorysId])
            ->addFieldToFilter('level', ['neq' => 0])
            ->setOrder('position', 'ASC')
            ->setOrder('level', 'ASC');
        $productCategoryId = null;
        $productSubCategoryId = null;

        /** @var \Magento\Catalog\Model\Category $category */
        foreach ($categoryCollection as $category) {
            if (isset($productCategoryId) && isset($productSubCategoryId)) {
                break;
            }

            switch ($category->getLevel()) {
                case 2:
                    $productCategoryId = $category->getId();
                    break;
                case 3:
                    $productSubCategoryId = $category->getId();
                    break;
            }
        }

        if (isset($productCategoryId)) {
            /** @var \Magento\Catalog\Model\Category $mainCategory */
            $mainCategory = $this->categoryFactory->create();
            $this->categoryResourceModel->load($mainCategory, $productCategoryId);
            $mainCategoryName = $mainCategory->getName();
        } else {
            $mainCategoryName = null;
        }

        if (isset($productSubCategoryId)) {
            /** @var \Magento\Catalog\Model\Category $subCategory */
            $subCategory = $this->categoryFactory->create();
            $this->categoryResourceModel->load($subCategory, $productSubCategoryId);
            $subCategoryName = $subCategory->getName();
        } else {
            $subCategoryName = null;
        }

        $itemPriceInclTax = $item->getPriceInclTax() ?? 0;

        $itemPrice = floatval(number_format($itemPriceInclTax, 2, '.', ''));

        if ($itemPrice <= 0) {
            if ($item->getParentItem()) {
                $type = $item->getParentItem()->getProductType();
                if ($type === 'configurable' || $type === 'bundle') {
                    $itemPriceInclTax = $item->getParentItem()->getPriceInclTax() ?? 0;

                    $itemPrice = floatval(number_format($itemPriceInclTax, 2, '.', ''));
                }
            }
        }

        $product = [];
        $product['itemName'] = $item->getName();
        $product['itemPrice'] = $itemPrice;
        $product['itemQuantity'] = (int)$item->getQty();
        $product['itemIsDigital'] = (bool) $item->getIsVirtual();
        $product['itemCategory'] = $mainCategoryName;
        $product['itemSubCategory'] = $subCategoryName;
        $product['itemId'] = $item->getSku();
        $product['itemImage'] = $productImageUrl;
        $product['itemUrl'] = $item->getProduct()->getProductUrl();
        $product['itemWeight'] = $item->getProduct()->getWeight();
        $product['shipmentId'] = null;
        $product['subscription'] = ($this->subscriptionFactory->create())();
        $product['giftMessage'] = ($this->giftMessageFactory->create())();
        $product['registryId'] = ($this->registryIdFactory->create())();

        return $product;
    }

    /**
     * Get product image method.
     *
     * @param mixed $product
     * @return string|null
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function getProductImage($product)
    {
        $productImage = $product->getImage();

        if (isset($productImage)) {
            $productImageUrl = $this->storeManagerInterface->getStore()
                    ->getBaseUrl(
                        \Magento\Framework\UrlInterface::URL_TYPE_MEDIA
                    ) . 'catalog/product' . $productImage;
        } else {
            $productImageUrl = null;
        }

        return $productImageUrl;
    }
}
