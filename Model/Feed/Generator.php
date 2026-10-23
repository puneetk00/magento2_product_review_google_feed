<?php

namespace Exportaccelerator\Productfeed\Model\Feed;

use Magento\Catalog\Model\Product\Attribute\Source\Status as ProductStatus;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\Exception\LocalizedException;
use Magento\Review\Model\ResourceModel\Rating\Option\Vote\CollectionFactory as VoteCollectionFactory;
use Magento\Review\Model\ResourceModel\Review\CollectionFactory as ReviewCollectionFactory;
use Magento\Review\Model\Review;
use Magento\Review\Model\ReviewFactory;
use Magento\Store\Api\Data\StoreInterface;
use Psr\Log\LoggerInterface;

class Generator
{
    private const PRODUCT_ATTRIBUTES = ['brand', 'manufacturer', 'gtin', 'upc', 'mpn'];
    private const PRODUCT_REVIEW_SCHEMA = 'http://www.google.com/shopping/reviews/schema/product/2.3/product_reviews.xsd';

    private ReviewCollectionFactory $reviewCollectionFactory;
    private ReviewFactory $reviewFactory;
    private VoteCollectionFactory $voteCollectionFactory;
    private ProductCollectionFactory $productCollectionFactory;
    private EavConfig $eavConfig;
    private LoggerInterface $logger;

    public function __construct(
        ReviewCollectionFactory $reviewCollectionFactory,
        ReviewFactory $reviewFactory,
        VoteCollectionFactory $voteCollectionFactory,
        ProductCollectionFactory $productCollectionFactory,
        EavConfig $eavConfig,
        LoggerInterface $logger
    ) {
        $this->reviewCollectionFactory = $reviewCollectionFactory;
        $this->reviewFactory = $reviewFactory;
        $this->voteCollectionFactory = $voteCollectionFactory;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->eavConfig = $eavConfig;
        $this->logger = $logger;
    }

    public function generate(StoreInterface $store): string
    {
        $writer = new \XMLWriter();
        if (!$writer->openMemory()) {
            throw new LocalizedException(__('Unable to initialize product review feed XML.'));
        }

        $writer->startDocument('1.0', 'UTF-8');
        $writer->startElement('feed');
        $writer->writeAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $writer->writeAttribute('xsi:noNamespaceSchemaLocation', self::PRODUCT_REVIEW_SCHEMA);
        $writer->writeElement('version', '2.3');
        $writer->startElement('publisher');
        $this->writeElement($writer, 'name', $store->getName());
        $writer->endElement();
        $writer->startElement('reviews');

        $productReviewEntityId = $this->reviewFactory->create()->getEntityIdByCode(Review::ENTITY_PRODUCT_CODE);
        if ($productReviewEntityId) {
            $this->writeReviews($writer, $store, (int)$productReviewEntityId);
        }

        $writer->endElement();
        $writer->endElement();
        $writer->endDocument();

        $xml = $writer->outputMemory();
        if (!is_string($xml)) {
            throw new LocalizedException(__('Unable to generate product review feed XML.'));
        }

        return $xml;
    }

    private function writeReviews(\XMLWriter $writer, StoreInterface $store, int $entityId): void
    {
        $reviews = $this->reviewCollectionFactory->create();
        $reviews->addStoreFilter((int)$store->getId())
            ->addFieldToFilter('main_table.entity_id', $entityId)
            ->setOrder('main_table.review_id', 'ASC')
            ->load();

        $reviewItems = $reviews->getItems();
        if (!$reviewItems) {
            return;
        }

        $reviewIds = [];
        $productIds = [];
        foreach ($reviewItems as $review) {
            $reviewIds[] = (int)$review->getId();
            $productIds[] = (int)$review->getEntityPkValue();
        }

        $products = $this->getProducts($store, array_unique($productIds));
        $ratings = $this->getRatings($reviewIds, (int)$store->getId());

        foreach ($reviewItems as $review) {
            $productId = (int)$review->getEntityPkValue();
            if (isset($products[$productId])) {
                $this->writeReview($writer, $review, $products[$productId], $ratings[(int)$review->getId()] ?? []);
            }
        }
    }

    private function getProducts(StoreInterface $store, array $productIds): array
    {
        $collection = $this->productCollectionFactory->create();
        $collection->setStoreId((int)$store->getId())
            ->addStoreFilter((int)$store->getId())
            ->addIdFilter($productIds)
            ->addAttributeToSelect(['sku', 'name', 'status'])
            ->addAttributeToFilter('status', ProductStatus::STATUS_ENABLED)
            ->addUrlRewrite();

        foreach (self::PRODUCT_ATTRIBUTES as $attributeCode) {
            if ($this->eavConfig->getAttribute('catalog_product', $attributeCode)->getId()) {
                $collection->addAttributeToSelect($attributeCode);
            }
        }

        $products = [];
        foreach ($collection as $product) {
            $products[(int)$product->getId()] = $product;
        }

        return $products;
    }

    private function getRatings(array $reviewIds, int $storeId): array
    {
        $votes = $this->voteCollectionFactory->create();
        $votes->addFieldToFilter('main_table.review_id', ['in' => $reviewIds])
            ->setStoreFilter($storeId);

        $ratings = [];
        foreach ($votes as $vote) {
            $percent = (int)$vote->getPercent();
            if ($percent > 0 && $percent <= 100) {
                $ratings[(int)$vote->getReviewId()][] = $percent;
            }
        }

        return $ratings;
    }

    private function writeReview(
        \XMLWriter $writer,
        Review $review,
        \Magento\Catalog\Model\Product $product,
        array $ratingPercents
    ): void {
        $content = $this->normalizeText($review->getDetail());
        $productUrl = (string)$product->getProductUrl();
        if ($content === '' || !$ratingPercents || trim($productUrl) === '') {
            $this->logger->warning(
                'Product review omitted from the Google feed because it lacks required content, rating, or URL.',
                ['review_id' => (int)$review->getId()]
            );
            return;
        }

        $writer->startElement('review');
        $this->writeElement($writer, 'review_id', 'magento-' . (int)$review->getId());
        $writer->startElement('reviewer');
        $writer->startElement('name');
        $writer->writeAttribute('is_anonymous', 'true');
        $writer->text('Anonymous');
        $writer->endElement();
        $writer->endElement();
        $createdAt = new \DateTimeImmutable((string)$review->getCreatedAt(), new \DateTimeZone('UTC'));
        $this->writeElement(
            $writer,
            'review_timestamp',
            $createdAt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z')
        );
        $this->writeElement($writer, 'title', $review->getTitle());
        $this->writeElement($writer, 'content', $content);

        $writer->startElement('review_url');
        $writer->writeAttribute('type', 'group');
        $writer->text($productUrl);
        $writer->endElement();

        $overall = round(array_sum($ratingPercents) / count($ratingPercents) / 20, 2);
        $writer->startElement('ratings');
        $writer->startElement('overall');
        $writer->writeAttribute('min', '1');
        $writer->writeAttribute('max', '5');
        $writer->text(number_format($overall, 2, '.', ''));
        $writer->endElement();
        $writer->endElement();

        $writer->startElement('products');
        $writer->startElement('product');
        $writer->startElement('product_ids');
        $this->writeProductIdentifier($writer, 'gtins', 'gtin', $this->getProductAttribute($product, ['gtin', 'upc']));
        $this->writeProductIdentifier($writer, 'mpns', 'mpn', $this->getProductAttribute($product, ['mpn']));
        $this->writeProductIdentifier($writer, 'skus', 'sku', $product->getSku());
        $this->writeProductIdentifier(
            $writer,
            'brands',
            'brand',
            $this->getProductAttribute($product, ['brand', 'manufacturer'], true)
        );
        $writer->endElement();
        $this->writeElement($writer, 'product_name', $product->getName());
        $this->writeElement($writer, 'product_url', $productUrl);
        $writer->endElement();
        $writer->endElement();
        $writer->endElement();
    }

    private function writeProductIdentifier(
        \XMLWriter $writer,
        string $container,
        string $element,
        ?string $value
    ): void {
        if ($value === null || $value === '') {
            return;
        }

        $writer->startElement($container);
        $this->writeElement($writer, $element, $value);
        $writer->endElement();
    }

    private function getProductAttribute(
        \Magento\Catalog\Model\Product $product,
        array $attributeCodes,
        bool $useOptionLabel = false
    ): ?string {
        foreach ($attributeCodes as $attributeCode) {
            $value = $product->getData($attributeCode);
            if ($value === null || $value === '') {
                continue;
            }

            if ($useOptionLabel) {
                $label = $product->getAttributeText($attributeCode);
                if (is_string($label) && $label !== '') {
                    return $label;
                }
                if (is_array($label) && $label) {
                    return implode(', ', $label);
                }

                $attribute = $product->getResource()->getAttribute($attributeCode);
                if ($attribute && in_array($attribute->getFrontendInput(), ['select', 'multiselect'], true)) {
                    continue;
                }
            }

            return (string)$value;
        }

        return null;
    }

    private function writeElement(\XMLWriter $writer, string $name, ?string $value): void
    {
        $value = $this->normalizeText($value);
        if ($value !== '') {
            $writer->writeElement($name, $value);
        }
    }

    private function normalizeText(?string $value): string
    {
        $value = html_entity_decode(strip_tags((string)$value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value) ?? '';
    }
}
