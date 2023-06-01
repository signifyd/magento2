<?php

namespace Signifyd\Connect\Model\Api;

use Magento\Framework\App\ProductMetadataInterface;

class MerchantPlatform
{
    /**
     * @var ProductMetadataInterface
     */
    protected $productMetadataInterface;

    /**
     * @param ProductMetadataInterface $productMetadataInterface
     */
    public function __construct(
        ProductMetadataInterface $productMetadataInterface
    ) {
        $this->productMetadataInterface = $productMetadataInterface;
    }

    /**
     * Construct a new MerchantPlatform object
     * @return array
     */
    public function __invoke()
    {
        $merchantPlataform = [];
        $merchantPlataform['name'] = 'Magento';
        $merchantPlataform['version'] = $this->productMetadataInterface->getVersion();
        return $merchantPlataform;
    }
}