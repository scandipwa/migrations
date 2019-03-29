<?php
/**
 * ScandiPWA_Migrations
 *
 * @category    Scandiweb
 * @package     ScandiPWA_Migrations
 * @author      Vladimirs Mihnovics <info@scandiweb.com>
 * @copyright   Copyright (c) 2019 Scandiweb, Ltd (https://scandiweb.com)
 */


namespace  ScandiPWA\Migrations\Setup\Patch\Data;

use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchRevertableInterface;

/**
 */
class AddGroupedProduct
    implements DataPatchInterface,
    PatchRevertableInterface
{
    /**
     * @var \Magento\Framework\Setup\ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    /**
     * @param \Magento\Framework\Setup\ModuleDataSetupInterface $moduleDataSetup
     */
    public function __construct(
        \Magento\Framework\Setup\ModuleDataSetupInterface $moduleDataSetup,
        \Magento\Framework\App\State $appState,
        \Magento\Catalog\Api\Data\ProductInterfaceFactory $productFactory,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry,
        \Magento\Catalog\Model\Product\Copier $copier

    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->_appState = $appState;
        $this->productFactory = $productFactory;
        $this->productRepository = $productRepository;
        $this->stockRegistry = $stockRegistry;
        $this->productCopier = $copier;
    }

    /**
     * {@inheritdoc}
     */
    public function apply()
    {
        $this->_appState->setAreaCode('adminhtml');
        $this->moduleDataSetup->getConnection()->startSetup();

        try {
            // load product by id
            $productOld = $linkedProduct = $this->productRepository->getById(2065);

            //duplicate product
            $product = $this->productCopier->copy($productOld);

            //set product props
            $product->setSku('branded-casual-set');
            $product->setName('Branded Casual Set');
            $product->setTypeId('grouped');
            $product->setVisibility(4);
            $product->setPrice(1);
            $product->setAttributeSetId(9);
            $product->setStatus(\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED);

            //Add children
            $product = $this->addChildrenProducts($product);
            $product->save();

            //Add stock
            $stockItem = $this->stockRegistry->getStockItemBySku($product->getSku());
            $stockItem->setIsInStock(true);
            $stockItem->setQty(100);
            $this->stockRegistry->updateStockItemBySku($product->getSku(), $stockItem);
        } catch (Exception $e) {
            echo 'Caught exception: ',  $e->getMessage(), "\n";
        }

        $this->moduleDataSetup->getConnection()->endSetup();
    }

    private function addChildrenProducts($product) {
        $childrenIds = array(1645, 1945, 1901);
        $associated = array();
        $position = 0;
        foreach($childrenIds as $productId){
            $position++;
            $linkedProduct = $this->productRepository->getById($productId);
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance(); // instance of object manager

            /** @var \Magento\Catalog\Api\Data\ProductLinkInterface $productLink */
            $productLink = $objectManager->create(\Magento\Catalog\Api\Data\ProductLinkInterface::class);

            $productLink->setSku($product->getSku())
                ->setLinkType('associated')
                ->setLinkedProductSku($linkedProduct->getSku())
                ->setLinkedProductType($linkedProduct->getTypeId())
                ->setPosition($position)
                ->getExtensionAttributes()
                ->setQty(100);

            $associated[] = $productLink;
        }
        $product->setProductLinks($associated);

        return $product;
    }

    public function revert()
    {
        $this->moduleDataSetup->getConnection()->startSetup();
        $this->moduleDataSetup->getConnection()->endSetup();
    }

    /**
     * {@inheritdoc}
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getAliases()
    {
        return [];
    }
}