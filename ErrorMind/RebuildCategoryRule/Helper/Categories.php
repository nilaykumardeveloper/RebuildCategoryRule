<?php
namespace ErrorMind\RebuildCategoryRule\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\ResourceModel\Category\Collection as CategoryCollection;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Store\Model\Store;
use Magento\VisualMerchandiser\Model\Rules;
use Magento\VisualMerchandiser\Model\RulesFactory;
use Magento\Catalog\Model\Indexer\Category\Product as CategoryProductIndexer;
use Magento\Catalog\Model\Indexer\Category\ProductFactory as CategoryProductIndexerFactory;
use Psr\Log\LoggerInterface;

/**
 * Class Categories
 * @package ErrorMind\RebuildCategoryRule\Helper
 */
class Categories extends AbstractHelper
{

    /**
     * Rules model
     * @var Rules
     */
    protected $rules;

    /**
     * Category Collection
     * @var CategoryCollection
     */
    protected $categoryCollection;

    /** @var LoggerInterface */
    protected $logger;


    /**
     * Category Product Indexer model
     * @var CategoryProductIndexer
     */
    protected $categoryProductIndexer;

    /**
     * Categories constructor.
     * @param Context $context
     * @param RulesFactory $rulesFactory
     * @param CategoryCollectionFactory $collectionFactory
     * @param CategoryProductIndexerFactory $categoryProductIndexerFactory
     */
    public function __construct(
        Context $context,
        RulesFactory $rulesFactory,
        CategoryCollectionFactory $collectionFactory,
        CategoryProductIndexerFactory $categoryProductIndexerFactory,
        LoggerInterface $logger
    )
    {

        $this->rules = $rulesFactory->create();
        $this->categoryCollection = $collectionFactory->create();
        $this->categoryProductIndexer = $categoryProductIndexerFactory->create();
        $this->logger = $logger;
        parent::__construct($context);
    }

    /**
     * Get collection of Visual Merchandiser categories
     *
     * @param $storeId
     * @return CategoryCollection
     */
    public function getSmartCategoryCollection($storeId = Store::DEFAULT_STORE_ID)
    {
        $this->categoryCollection->setStoreId($storeId);

        /** @var Category $category */
        foreach ($this->categoryCollection as $key => $category) {
            $rule = $this->rules->loadByCategory($category);
            if (!$rule->getId() || !$rule->getIsActive()) {
                $this->categoryCollection->removeItemByKey($key);
            }
        }
        return $this->categoryCollection;
    }

    /**
     * Rebuild all Visual Merchandiser categories
     *
     * @param int $storeId
     * @return \int[] Rebuilt Category IDs
     * @throws \Exception
     */
    public function rebuildAll($storeId = Store::DEFAULT_STORE_ID)
    {
        $rebuiltIds = [];
        $smartCategoryCollection = $this->getSmartCategoryCollection($storeId);

        /** @var Category $category */
        foreach ($smartCategoryCollection as $smartCategory) {
            $smartCategoryId = (int)$smartCategory->getId();
            try{
                $smartCategory
                ->setStoreId($storeId)
                ->load($smartCategoryId)
                ->save();
                $this->logger->debug(' category Id: '.$smartCategoryId);
            }
            catch(\Exception $e){
                $this->logger->debug($e->getMessage().' category Id: '.$smartCategoryId);
            }

            $rebuiltIds[] = $smartCategoryId;
        }

        $this->categoryProductIndexer->executeList($rebuiltIds);

        return $rebuiltIds;
    }

}