<?php
/**
 *
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\Catalog\Model\Product\Gallery;

use Magento\Catalog\Api\Data\ProductAttributeMediaGalleryEntryInterface;
use Magento\Catalog\Api\Data\ProductInterface as Product;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\StateException;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class GalleryManagement implements \Magento\Catalog\Api\ProductAttributeMediaGalleryManagementInterface
{
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var \Magento\Catalog\Api\ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * @var \Magento\Catalog\Model\Product\Gallery\ContentValidator
     */
    protected $contentValidator;

    /**
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Catalog\Api\ProductRepositoryInterface $productRepository
     * @param ContentValidator $contentValidator
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\Catalog\Model\Product\Gallery\ContentValidator $contentValidator
    ) {
        $this->productRepository = $productRepository;
        $this->storeManager = $storeManager;
        $this->contentValidator = $contentValidator;
    }

    /**
     * Retrieve backend model of product media gallery attribute
     *
     * @param Product $product
     * @return \Magento\Eav\Model\Entity\Attribute\Backend\AbstractBackend
     * @throws StateException
     */
    protected function getGalleryAttributeBackend(Product $product)
    {
        $galleryAttributeBackend = $product->getGalleryAttributeBackend();
        if ($galleryAttributeBackend == null) {
            throw new StateException(__('Requested product does not support images.'));
        }
        return $galleryAttributeBackend;
    }

    /**
     * {@inheritdoc}
     */
    public function create($product)
    {
        try {
            $this->storeManager->getStore($product->getStoreId());
        } catch (\Exception $exception) {
            throw new NoSuchEntityException(__('There is no store with provided ID.'));
        }
        /** @var $entry ProductAttributeMediaGalleryEntryInterface */
        $entry = $product->getCustomAttribute('media_gallery')->getValue();
        $entryContent = $entry->getContent();

        if (!$this->contentValidator->isValid($entryContent)) {
            throw new InputException(__('The image content is not valid.'));
        }
        $product = $this->productRepository->get($product->getSku());

        $existingMediaGalleryEntries = $product->getMediaGalleryEntries();
        $existingEntryIds = [];
        if ($existingMediaGalleryEntries == null) {
            $existingMediaGalleryEntries = [$entry];
        } else {
            foreach ($existingMediaGalleryEntries as $existingEntries) {
                $existingEntryIds[$existingEntries->getId()] = $existingEntries->getId();
            }
            $existingMediaGalleryEntries[] = $entry;
        }
        $product->setMediaGalleryEntries($existingMediaGalleryEntries);
        try {
            $this->productRepository->save($product);
        } catch (InputException $inputException) {
            throw $inputException;
        } catch (\Exception $e) {
            throw new StateException(__('Cannot save product.'));
        }

        $product = $this->productRepository->get($product->getSku());
        foreach ($product->getMediaGalleryEntries() as $entry) {
            if (!isset($existingEntryIds[$entry->getId()])) {
                return $entry->getId();
            }
        }
        throw new StateException(__('Failed to save new media gallery entry.'));
    }

    /**
     * {@inheritdoc}
     */
    public function update($sku, ProductAttributeMediaGalleryEntryInterface $entry, $storeId = 0)
    {
        try {
            $this->storeManager->getStore($storeId);
        } catch (\Exception $exception) {
            throw new NoSuchEntityException(__('There is no store with provided ID.'));
        }
        $product = $this->productRepository->get($sku);
        $existingMediaGalleryEntries = $product->getMediaGalleryEntries();
        if ($existingMediaGalleryEntries == null) {
            throw new NoSuchEntityException(__('There is no image with provided ID.'));
        }
        $found = false;
        foreach ($existingMediaGalleryEntries as $key => $existingEntry) {
            if ($existingEntry->getId() == $entry->getId()) {
                $found = true;
                $existingMediaGalleryEntries[$key] = $entry;
                break;
            }
        }
        if (!$found) {
            throw new NoSuchEntityException(__('There is no image with provided ID.'));
        }
        $product->setMediaGalleryEntries($existingMediaGalleryEntries);
        $product->setStoreId($storeId);

        try {
            $this->productRepository->save($product);
        } catch (\Exception $exception) {
            throw new StateException(__('Cannot save product.'));
        }
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function remove($sku, $entryId)
    {
        $product = $this->productRepository->get($sku);
        $existingMediaGalleryEntries = $product->getMediaGalleryEntries();
        if ($existingMediaGalleryEntries == null) {
            throw new NoSuchEntityException(__('There is no image with provided ID.'));
        }
        $found = false;
        foreach ($existingMediaGalleryEntries as $key => $entry) {
            if ($entry->getId() == $entryId) {
                unset($existingMediaGalleryEntries[$key]);
                $found = true;
                break;
            }
        }
        if (!$found) {
            throw new NoSuchEntityException(__('There is no image with provided ID.'));
        }
        $product->setMediaGalleryEntries($existingMediaGalleryEntries);
        $this->productRepository->save($product);
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function get($sku, $imageId)
    {
        try {
            $product = $this->productRepository->get($sku);
        } catch (\Exception $exception) {
            throw new NoSuchEntityException(__('Such product doesn\'t exist'));
        }

        $mediaGalleryEntries = $product->getMediaGalleryEntries();
        foreach ($mediaGalleryEntries as $entry) {
            if ($entry->getId() == $imageId) {
                return $entry;
            }
        }

        throw new NoSuchEntityException(__('Such image doesn\'t exist'));
    }

    /**
     * {@inheritdoc}
     */
    public function getList($sku)
    {
        /** @var \Magento\Catalog\Model\Product $product */
        $product = $this->productRepository->get($sku);

        return $product->getMediaGalleryEntries();
    }
}
