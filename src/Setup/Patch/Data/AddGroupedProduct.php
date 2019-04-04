<?php
/**
 * ScandiPWA_Migrations
 *
 * @category    Scandiweb
 * @package     ScandiPWA_Migrations
 * @author      Vladimirs Mihnovics <info@scandiweb.com>
 * @copyright   Copyright (c) 2019 Scandiweb, Ltd (https://scandiweb.com)
 */


namespace ScandiPWA\Migrations\Setup\Patch\Data;

use Magento\Catalog\Api\Data\ProductInterfaceFactory;
use Magento\Catalog\Api\Data\ProductLinkInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Copier;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\State;
use Magento\Framework\Filesystem;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchRevertableInterface;
use Magento\Framework\Filesystem\Directory\WriteInterface;

/**
 */
class AddGroupedProduct implements DataPatchInterface, PatchRevertableInterface
{
    /**
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;
    
    /**
     * @var State
     */
    private $appState;
    
    /**
     * @var ProductInterfaceFactory
     */
    private $productFactory;
    
    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;
    
    /**
     * @var StockRegistryInterface
     */
    private $stockRegistry;
    
    /**
     * @var Copier
     */
    private $productCopier;
    
    /**
     * @var WriteInterface
     */
    private $mediaDirectory;
    
    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        State $appState,
        ProductInterfaceFactory $productFactory,
        ProductRepositoryInterface $productRepository,
        StockRegistryInterface $stockRegistry,
        Copier $copier,
        Filesystem $filesystem
    )
    {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->appState = $appState;
        $this->productFactory = $productFactory;
        $this->productRepository = $productRepository;
        $this->stockRegistry = $stockRegistry;
        $this->productCopier = $copier;
        $this->mediaDirectory = $filesystem->getDirectoryWrite(DirectoryList::MEDIA);
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
    public function apply()
    {
        if (!$this->appState->getAreaCode()) {
            $this->appState->setAreaCode('adminhtml');
        }
        $this->moduleDataSetup->getConnection()->startSetup();
        
        try {
            /**
             * @var $productOld Product
             */
            $productOld = $this->productRepository->getById(1699);
            
            if ($productOld) {
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
                $product->setStatus(Status::STATUS_ENABLED);
                
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
            
        } catch (\Exception $e) {
            echo 'Caught exception: ', $e->getMessage(), "\n";
        }
        
        $this->moduleDataSetup->getConnection()->endSetup();
    }
    
    private function addChildrenProducts($product)
    {
        $childrenIds = array(1645, 1945, 1901);
        $associated = array();
        $position = 0;
        
        foreach ($childrenIds as $productId) {
            $linkedProduct = $this->productRepository->getById($productId);
            
            if ($linkedProduct) {
                $objectManager = ObjectManager::getInstance();
                
                /** @var ProductLinkInterface $productLink */
                $productLink = $objectManager->create(ProductLinkInterface::class);
                
                $productLink->setSku($product->getSku())
                    ->setLinkType('associated')
                    ->setLinkedProductSku($linkedProduct->getSku())
                    ->setLinkedProductType($linkedProduct->getTypeId())
                    ->setPosition(++$position)
                    ->setQty(100)
                    ->getExtensionAttributes();
                
                $associated[] = $productLink;
            }
        }
        $product->setProductLinks($associated);
        
        return $product;
    }
    
    private function copyMedia($oldProduct)
    {
        $product = $this->productRepository->get('branded-casual-set');
        $oldProductMedia = $oldProduct->getMediaGalleryEntries();
        $newProductMedia = $product->getMediaGalleryEntries();
        
        foreach ($newProductMedia as $key => $galleryEntry) {
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
                } catch (\Exception $e) {
                    echo 'Caught exception: ', $e->getMessage(), "\n";
                }
            } else {
                unset($newProductMedia[$key]);
            }
        }
        
        $product->setMediaGalleryEntries($newProductMedia);
        
        try {
            $this->productRepository->save($product);
        } catch (\Exception $e) {
            echo 'Caught exception: ', $e->getMessage(), "\n";
        }
    }
    
    private function replace_extension($filename, $new_extension)
    {
        return substr_replace($filename, $new_extension, strrpos($filename, '.') + 1);
    }
    
    public function revert()
    {
        $this->moduleDataSetup->getConnection()->startSetup();
        $this->moduleDataSetup->getConnection()->endSetup();
    }
    
    /**
     * {@inheritdoc}
     */
    public function getAliases()
    {
        return [];
    }
}
