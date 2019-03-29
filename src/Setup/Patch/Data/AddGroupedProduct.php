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
        \Magento\Catalog\Model\Product\Copier $copier,
        \Magento\Framework\Filesystem $filesystem,
        \Magento\Framework\Filesystem\DirectoryList $directoryList

    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->_appState = $appState;
        $this->productFactory = $productFactory;
        $this->productRepository = $productRepository;
        $this->stockRegistry = $stockRegistry;
        $this->productCopier = $copier;
        $this->mediaDirectory = $filesystem->getDirectoryWrite($directoryList::MEDIA);
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
            $productOld = $linkedProduct = $this->productRepository->getById(1699);

            if($productOld) {
                //duplicate product
                $product = $this->productCopier->copy($productOld);

                //set product props
                $product->setSku('branded-casual-set');
                $product->setUrlKey('branded-casual-set');
                $product->setName('Branded Casual Set');
                $product->setTypeId('grouped');
                $product->setVisibility(4);
                $product->setPrice(1);
                $product->setAttributeSetId(9);
                $product->setStatus(\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED);

                //Add children
                $product = $this->addChildrenProducts($product);
                $product->save();

                //Copy images
                $this->copyMedia($productOld);

                //Add stock
                $stockItem = $this->stockRegistry->getStockItemBySku($product->getSku());
                $stockItem->setIsInStock(true);
                $stockItem->setQty(100);
                $this->stockRegistry->updateStockItemBySku($product->getSku(), $stockItem);
            }

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

            if($linkedProduct) {
                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

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
        }
        $product->setProductLinks($associated);

        return $product;
    }

    private function replace_extension($filename, $new_extension) {
        return substr_replace($filename , $new_extension, strrpos($filename , '.') +1);
    }

    private function copyMedia($oldProduct) {
        $product = $this->productRepository->get('branded-casual-set');
        $oldProductMedia = $oldProduct->getMediaGalleryEntries();
        $newProductMedia = $product->getMediaGalleryEntries();

        foreach($newProductMedia as $key => $galleryEntry){
            if (isset($oldProductMedia[$key])) {
                $thisFile = $oldProductMedia[$key]->getFile();
                $thatFile = $galleryEntry->getFile();

                try {
                    $this->mediaDirectory->copyFile(
                        'jpg/catalog/product' . $thisFile,
                        'jpg/catalog/product' . $thatFile
                    );
                    $this->mediaDirectory->copyFile(
                        'webp/catalog/product' . $this->replace_extension($thisFile, 'webp'),
                        'webp/catalog/product' . $this->replace_extension($thatFile, 'webp')
                    );
                    $this->mediaDirectory->copyFile(
                        'svg/catalog/product' . $this->replace_extension($thisFile, 'svg'),
                        'svg/catalog/product' . $this->replace_extension($thatFile, 'svg')
                    );
                }catch(\Exception $e)
                {
                    echo 'Caught exception: ',  $e->getMessage(), "\n";
                }
            } else {
                unset($newProductMedia[$key]);
            }
        }

        $product->setMediaGalleryEntries($newProductMedia);

        try {
            $this->productRepository->save($product);
        }catch(\Exception $e)
        {
            echo 'Caught exception: ',  $e->getMessage(), "\n";
        }
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