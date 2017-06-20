<?php
/**
 * Endpoint for Managing product url rewrites
 * Copyright (C) 2017 Ecomatic
 * 
 * This file is part of Ecomatic/UrlRewrite.
 * 
 * Ecomatic/UrlRewrite is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace Ecomatic\UrlRewrite\Controller\Index;
use Magento\Store\Model\Store;

class Index extends \Magento\Framework\App\Action\Action {

    protected $collection;
    protected $storeManager;
    protected $urlPersist;
    protected $productUrlRewriteGenerator;
	protected $storeRepository;
	
    /**
     * Constructor
     *
	 * @param \Magento\Framework\App\Action\Context $context
	 * @param \Magento\Framework\App\State $state
	 * @param \Magento\Catalog\Model\ResourceModel\Product\Collection $collection
	 * @param \Magento\CatalogUrlRewrite\Model\ProductUrlRewriteGenerator $productUrlRewriteGenerator
	 * @param \Magento\UrlRewrite\Model\UrlPersistInterface $urlPersist
	 * @param \Magento\Store\Model\StoreRepository $storeRepository
	 * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     */
    public function __construct(
		\Magento\Framework\App\Action\Context $context,
		\Magento\Framework\App\State $state,
        \Magento\Catalog\Model\ResourceModel\Product\Collection $collection,
        \Magento\CatalogUrlRewrite\Model\ProductUrlRewriteGenerator $productUrlRewriteGenerator,
        \Magento\UrlRewrite\Model\UrlPersistInterface $urlPersist,
		\Magento\Store\Model\StoreRepository $storeRepository,
		\Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
	//	$state->setAreaCode('adminhtml');
        $this->collection = $collection;
		$this->storeManager = $storeManager;
        $this->productUrlRewriteGenerator = $productUrlRewriteGenerator;
        $this->urlPersist = $urlPersist;
		$this->storeManager = $storeManager;
		$this->storeRepository = $storeRepository;
		parent::__construct($context);
    }

    /**
     * Execute view action
     *
     * @return void
     */
    public function execute(){
		$finnishedIds = array();
		try {
			$file = file_get_contents("./app/code/Ecomatic/UrlRewrite/Controller/Index/done", FILE_USE_INCLUDE_PATH);
			$finnishedIds = explode(",", $file);
		}
		catch (\Exception $e){}
		$stores = $this->storeRepository->getList();
		foreach ($stores as $store) {
			$storeId = $store["store_id"];
			$this->storeManager->setCurrentStore($storeId);
			$this->collection->addStoreFilter($storeId)->setStoreId($storeId);
			$this->collection->addAttributeToSelect(['url_path', 'url_key']);
			$list = $this->collection->load();
			foreach($list as $product){
				if (in_array($product->getId() . "_" . $storeId, $finnishedIds))
					continue;
				
				if($storeId === Store::DEFAULT_STORE_ID)
					$product->setStoreId($storeId);
				
				$this->urlPersist->deleteByData([
					\Magento\UrlRewrite\Service\V1\Data\UrlRewrite::ENTITY_ID => $product->getId(),
					\Magento\UrlRewrite\Service\V1\Data\UrlRewrite::ENTITY_TYPE => \Magento\CatalogUrlRewrite\Model\ProductUrlRewriteGenerator::ENTITY_TYPE,
					\Magento\UrlRewrite\Service\V1\Data\UrlRewrite::REDIRECT_TYPE => 0,
					\Magento\UrlRewrite\Service\V1\Data\UrlRewrite::STORE_ID => $storeId
				]);
				try {
					$this->urlPersist->replace($this->productUrlRewriteGenerator->generate($product));
					if (empty($finnishedIds)){
						file_put_contents("./app/code/Ecomatic/UrlRewrite/Controller/Index/done", $product->getId() . "_" . $storeId, FILE_USE_INCLUDE_PATH | FILE_APPEND);
						array_push($finnishedIds, $product->getId() . "_" . $storeId);
					}
					else {
						file_put_contents("./app/code/Ecomatic/UrlRewrite/Controller/Index/done", "," . $product->getId() . "_" . $storeId, FILE_USE_INCLUDE_PATH | FILE_APPEND);
					}
				}
				catch(\Exception $e) {}
			}
		}
    }
}