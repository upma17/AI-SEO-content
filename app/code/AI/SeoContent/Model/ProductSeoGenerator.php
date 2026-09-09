<?php
declare(strict_types=1);

namespace AI\SeoContent\Model;

use AI\SeoContent\Logger\Logger;
use AI\SeoContent\Model\Llm\LlmClientPool;
use AI\SeoContent\Model\Queue\Publisher as QueuePublisher;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Action as ProductAction;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;

class ProductSeoGenerator
{
    public const RESULT_SAVED = 'saved';
    public const RESULT_SKIPPED = 'skipped';
    public const RESULT_FAILED = 'failed';

    private ?string $lastProcessSummary = null;

    public function __construct(
        private readonly Config $config,
        private readonly LlmClientPool $llmClientPool,
        private readonly CollectionFactory $productCollectionFactory,
        private readonly CategoryCollectionFactory $categoryCollectionFactory,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ProductAction $productAction,
        private readonly StoreManagerInterface $storeManager,
        private readonly QueuePublisher $queuePublisher,
        private readonly Logger $logger,
        private readonly State $appState
    ) {
    }

    /**
     * Cron / default entry: enqueue to message queue or process synchronously.
     *
     * @return array{processed: int, saved: int, skipped: int, failed: int, enqueued: int}
     */
    public function processBatch(?int $storeId = null, bool $ignoreEnabledFlag = false): array
    {
        if ($this->shouldUseQueue($storeId, $ignoreEnabledFlag)) {
            return $this->enqueueBatch($storeId, $ignoreEnabledFlag);
        }

        return $this->processBatchSync($storeId, $ignoreEnabledFlag);
    }

    /**
     * Publish product jobs to the message queue (for large catalogs).
     *
     * @return array{processed: int, saved: int, skipped: int, failed: int, enqueued: int}
     */
    public function enqueueBatch(?int $storeId = null, bool $ignoreEnabledFlag = false): array
    {
        $stats = ['processed' => 0, 'saved' => 0, 'skipped' => 0, 'failed' => 0, 'enqueued' => 0];

        foreach ($this->getStores($storeId) as $store) {
            $currentStoreId = (int) $store->getId();

            if (!$ignoreEnabledFlag && !$this->config->isEnabled($currentStoreId)) {
                $this->logger->info(sprintf('Store %d: module disabled, skipping enqueue.', $currentStoreId));
                continue;
            }

            $limit = $this->config->getEnqueueBatchSize($currentStoreId);
            $collection = $this->buildEligibleCollection($currentStoreId, $limit);

            $this->logger->info(sprintf(
                'Store %d (%s): enqueuing up to %d products.',
                $currentStoreId,
                $store->getName(),
                min($limit, (int) $collection->getSize())
            ));

            foreach ($collection as $product) {
                $stats['processed']++;
                try {
                    $this->queuePublisher->publish(
                        (int) $product->getId(),
                        $currentStoreId,
                        (string) $product->getSku()
                    );
                    $stats['enqueued']++;
                } catch (\Throwable $e) {
                    $stats['failed']++;
                    $this->logger->error(sprintf(
                        'Failed to enqueue product_id=%d store_id=%d: %s',
                        (int) $product->getId(),
                        $currentStoreId,
                        $e->getMessage()
                    ));
                }
            }
        }

        return $stats;
    }

    /**
     * Process products synchronously (CLI --sync or when queue is disabled).
     *
     * @return array{processed: int, saved: int, skipped: int, failed: int, enqueued: int}
     */
    public function processBatchSync(?int $storeId = null, bool $ignoreEnabledFlag = false): array
    {
        $stats = ['processed' => 0, 'saved' => 0, 'skipped' => 0, 'failed' => 0, 'enqueued' => 0];

        foreach ($this->getStores($storeId) as $store) {
            $currentStoreId = (int) $store->getId();

            if (!$ignoreEnabledFlag && !$this->config->isEnabled($currentStoreId)) {
                $this->logger->info(sprintf('Store %d: module disabled, skipping.', $currentStoreId));
                continue;
            }

            $limit = $this->config->getBatchSize($currentStoreId);
            $collection = $this->buildEligibleCollection($currentStoreId, $limit);

            foreach ($collection as $product) {
                $stats['processed']++;
                $result = $this->processProduct((int) $product->getId(), $currentStoreId, $ignoreEnabledFlag);

                if ($result === self::RESULT_SAVED) {
                    $stats['saved']++;
                } elseif ($result === self::RESULT_SKIPPED) {
                    $stats['skipped']++;
                } else {
                    $stats['failed']++;
                }
            }
        }

        return $stats;
    }

    /**
     * Process a single product (used by queue consumer and sync mode).
     */
    public function processProduct(int $productId, int $storeId, bool $ignoreEnabledFlag = false): string
    {
        $this->lastProcessSummary = null;
        $this->ensureAreaCode();

        if (!$ignoreEnabledFlag && !$this->config->isEnabled($storeId)) {
            $this->lastProcessSummary = 'Module disabled for store.';
            return self::RESULT_SKIPPED;
        }

        try {
            $product = $this->productRepository->getById($productId, false, $storeId);
            $product->setStoreId($storeId);

            if (!$this->productNeedsProcessing($product, $storeId)) {
                $this->lastProcessSummary = 'All target fields already populated.';
                $this->logger->info(sprintf(
                    'Skipped product_id=%d store_id=%d — all target fields already populated.',
                    $productId,
                    $storeId
                ));
                return self::RESULT_SKIPPED;
            }

            $store = $this->storeManager->getStore($storeId);
            $categoryNames = $this->getCategoryNames($product->getCategoryIds());
            $prompt = $this->buildPrompt($product, $categoryNames, (string) $store->getName(), $storeId);

            $seo = $this->llmClientPool->generateSeoContent(
                $prompt,
                $this->shouldGenerateMetaKeyword($product, $storeId),
                $this->shouldGenerateShortDescription($product, $storeId)
            );

            if ($seo === null) {
                $this->lastProcessSummary = $this->llmClientPool->getLastError()
                    ?? 'LLM API returned no content.';
                return self::RESULT_FAILED;
            }

            $this->lastProcessSummary = $this->buildResultSummary($seo);

            if ($this->config->isDryRun($storeId)) {
                $this->logger->info(sprintf(
                    'DRY RUN store=%d sku=%s payload=%s',
                    $storeId,
                    $product->getSku(),
                    json_encode($seo, JSON_UNESCAPED_UNICODE)
                ));
                return self::RESULT_SKIPPED;
            }

            $this->applySeoToProduct($product, $seo, $storeId);
            $attributeData = $this->buildAttributeData($product, $seo, $storeId);
            if ($attributeData === []) {
                $this->lastProcessSummary = 'No SEO attributes selected for save.';
                return self::RESULT_SKIPPED;
            }

            $this->persistSeoAttributes((int) $product->getId(), $attributeData, $storeId);

            $this->logger->info(sprintf(
                'Saved SEO for store=%d sku=%s fields=%s',
                $storeId,
                $product->getSku(),
                implode(',', array_keys($attributeData))
            ));

            usleep($this->config->getRequestDelayMs($storeId) * 1000);

            return self::RESULT_SAVED;
        } catch (NoSuchEntityException $e) {
            $this->lastProcessSummary = $e->getMessage();
            $this->logger->error(sprintf('Product not found id=%d: %s', $productId, $e->getMessage()));
            return self::RESULT_FAILED;
        } catch (CouldNotSaveException $e) {
            $this->lastProcessSummary = $e->getMessage();
            $this->logger->error(sprintf(
                'Failed to save product id=%d: %s',
                $productId,
                $e->getMessage()
            ));
            return self::RESULT_FAILED;
        } catch (\Throwable $e) {
            $this->lastProcessSummary = $e->getMessage();
            $this->logger->error(sprintf(
                'Error processing product id=%d: %s',
                $productId,
                $e->getMessage()
            ));
            return self::RESULT_FAILED;
        }
    }

    public function getLastProcessSummary(): ?string
    {
        return $this->lastProcessSummary;
    }

    private function shouldUseQueue(?int $storeId, bool $ignoreEnabledFlag): bool
    {
        if ($storeId !== null) {
            return $this->config->useMessageQueue($storeId);
        }

        foreach ($this->getStores(null) as $store) {
            if ($this->config->useMessageQueue((int) $store->getId())) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return StoreInterface[]
     */
    private function getStores(?int $storeId): array
    {
        if ($storeId !== null) {
            return [$this->storeManager->getStore($storeId)];
        }

        $stores = [];
        foreach ($this->storeManager->getStores(true) as $store) {
            if ((int) $store->getId() === 0) {
                continue;
            }
            $stores[] = $store;
        }

        return $stores;
    }

    private function buildEligibleCollection(int $storeId, int $limit): Collection
    {
        $collection = $this->productCollectionFactory->create();
        $collection->setStoreId($storeId);
        $collection->addStoreFilter($storeId);
        $collection->addAttributeToSelect([
            'name',
            'sku',
            'description',
            'short_description',
            'meta_title',
            'meta_description',
            'meta_keyword',
        ]);
        $collection->addAttributeToFilter('status', Status::STATUS_ENABLED);
        $collection->addAttributeToFilter('visibility', ['neq' => Visibility::VISIBILITY_NOT_VISIBLE]);
        $this->applyEligibilityFilters($collection, $storeId);
        $collection->setPageSize($limit);
        $collection->setCurPage(1);

        return $collection;
    }

    private function applyEligibilityFilters(Collection $collection, int $storeId): void
    {
        $orFilters = [];

        if ($this->config->onlyEmptyMeta($storeId)) {
            $orFilters[] = ['attribute' => 'meta_description', ['null' => true]];
            $orFilters[] = ['attribute' => 'meta_description', ['eq' => '']];
        }

        if ($this->config->generateMetaKeyword($storeId)) {
            $orFilters[] = ['attribute' => 'meta_keyword', ['null' => true]];
            $orFilters[] = ['attribute' => 'meta_keyword', ['eq' => '']];
        }

        if ($this->config->generateShortDescription($storeId)
            && $this->config->onlyEmptyShortDescription($storeId)
        ) {
            $orFilters[] = ['attribute' => 'short_description', ['null' => true]];
            $orFilters[] = ['attribute' => 'short_description', ['eq' => '']];
        }

        if ($orFilters !== []) {
            $collection->addAttributeToFilter($orFilters, null, 'left');
        }
    }

    private function productNeedsProcessing(Product $product, int $storeId): bool
    {
        if ($this->config->onlyEmptyMeta($storeId) && !$this->isEmpty((string) $product->getMetaDescription())) {
            if (!$this->shouldGenerateMetaKeyword($product, $storeId)
                && !$this->shouldGenerateShortDescription($product, $storeId)
            ) {
                return false;
            }
        }

        $needsMeta = $this->config->onlyEmptyMeta($storeId)
            ? $this->isEmpty((string) $product->getMetaDescription())
            : true;

        $needsKeyword = $this->shouldGenerateMetaKeyword($product, $storeId);
        $needsShort = $this->shouldGenerateShortDescription($product, $storeId);

        return $needsMeta || $needsKeyword || $needsShort;
    }

    private function shouldGenerateMetaKeyword(Product $product, int $storeId): bool
    {
        if (!$this->config->generateMetaKeyword($storeId)) {
            return false;
        }

        return $this->isEmpty((string) $product->getMetaKeyword());
    }

    private function shouldGenerateShortDescription(Product $product, int $storeId): bool
    {
        if (!$this->config->generateShortDescription($storeId)) {
            return false;
        }

        if ($this->config->onlyEmptyShortDescription($storeId)) {
            return $this->isEmpty(strip_tags((string) $product->getShortDescription()));
        }

        return true;
    }

    /**
     * @param array<string, string> $seo
     * @return array<string, string>
     */
    private function buildAttributeData(Product $product, array $seo, int $storeId): array
    {
        $attributeData = [];

        if (!$this->config->onlyEmptyMeta($storeId) || $this->isEmpty((string) $product->getMetaDescription())) {
            $attributeData['meta_title'] = $seo['meta_title'];
            $attributeData['meta_description'] = $seo['meta_description'];
        }

        if (isset($seo['meta_keyword']) && $this->shouldGenerateMetaKeyword($product, $storeId)) {
            $attributeData['meta_keyword'] = $seo['meta_keyword'];
        }

        if (isset($seo['short_description']) && $this->shouldGenerateShortDescription($product, $storeId)) {
            $attributeData['short_description'] = $seo['short_description'];
        }

        return $attributeData;
    }

    /**
     * Persist SEO attributes at the target store and default scope when needed.
     *
     * Admin "All Store Views" reads store_id=0. On single-store sites we mirror
     * generated SEO to default scope so values are visible without switching scope.
     *
     * @param array<string, string> $attributeData
     */
    private function persistSeoAttributes(int $productId, array $attributeData, int $storeId): void
    {
        $this->productAction->updateAttributes([$productId], $attributeData, $storeId);

        if ($storeId === 0 || !$this->shouldMirrorSeoToDefaultStore()) {
            return;
        }

        $this->productAction->updateAttributes([$productId], $attributeData, 0);
    }

    private function shouldMirrorSeoToDefaultStore(): bool
    {
        return count($this->getStores(null)) === 1;
    }

    /**
     * @param array<string, string> $seo
     */
    private function applySeoToProduct(Product $product, array $seo, int $storeId): void
    {
        if (!$this->config->onlyEmptyMeta($storeId) || $this->isEmpty((string) $product->getMetaDescription())) {
            $product->setData('meta_title', $seo['meta_title']);
            $product->setData('meta_description', $seo['meta_description']);
        }

        if (isset($seo['meta_keyword']) && $this->shouldGenerateMetaKeyword($product, $storeId)) {
            $product->setData('meta_keyword', $seo['meta_keyword']);
        }

        if (isset($seo['short_description']) && $this->shouldGenerateShortDescription($product, $storeId)) {
            $product->setData('short_description', $seo['short_description']);
        }
    }

    private function isEmpty(string $value): bool
    {
        return trim($value) === '';
    }

    private function ensureAreaCode(): void
    {
        try {
            $this->appState->setAreaCode(Area::AREA_ADMINHTML);
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            // Area code already set by cron, consumer, or another entry point.
        }
    }

    /**
     * @param int[]|string[] $categoryIds
     * @return string[]
     */
    private function getCategoryNames(array $categoryIds): array
    {
        if ($categoryIds === []) {
            return [];
        }

        $collection = $this->categoryCollectionFactory->create();
        $collection->addAttributeToSelect('name');
        $collection->addAttributeToFilter('entity_id', ['in' => $categoryIds]);

        $names = [];
        foreach ($collection as $category) {
            $names[] = (string) $category->getName();
        }

        return $names;
    }

    private function buildPrompt(
        Product $product,
        array $categoryNames,
        string $storeName,
        int $storeId
    ): string {
        $name = (string) $product->getName();
        $sku = (string) $product->getSku();
        $shortDescription = strip_tags((string) $product->getShortDescription());
        $description = strip_tags((string) $product->getDescription());
        $categories = $categoryNames !== [] ? implode(', ', $categoryNames) : 'General';

        $fields = [
            '- meta_title: max 60 characters, include primary keyword, no quotes',
            '- meta_description: max 155 characters, compelling CTA, no quotes',
        ];
        $jsonKeys = ['meta_title', 'meta_description'];

        if ($this->shouldGenerateMetaKeyword($product, $storeId)) {
            $fields[] = '- meta_keyword: comma-separated, 5-8 relevant keywords, max 255 characters';
            $jsonKeys[] = 'meta_keyword';
        }

        if ($this->shouldGenerateShortDescription($product, $storeId)) {
            $fields[] = '- short_description: 2-4 sentences, benefit-focused, plain text (no HTML), max 500 characters';
            $jsonKeys[] = 'short_description';
        }

        $fieldRules = implode("\n", $fields);
        $jsonExample = '{' . implode(',', array_map(
            static fn (string $key): string => '"' . $key . '":"..."',
            $jsonKeys
        )) . '}';

        return <<<PROMPT
You are an expert e-commerce SEO copywriter for Magento / Adobe Commerce.

Generate SEO and catalog content for this product.

Store view: {$storeName}
Product name: {$name}
SKU: {$sku}
Categories: {$categories}
Existing short description: {$shortDescription}
Existing long description: {$description}

Rules:
{$fieldRules}
- Do not invent features not implied by the product data
- Return JSON only with these keys: {$jsonExample}

Example:
{$jsonExample}
PROMPT;
    }

    /**
     * @param array<string, string> $seo
     */
    private function buildResultSummary(array $seo): string
    {
        $parts = [];
        foreach (array_keys($seo) as $field) {
            $parts[] = (string) $field;
        }

        return 'Generated: ' . implode(', ', $parts);
    }
}
