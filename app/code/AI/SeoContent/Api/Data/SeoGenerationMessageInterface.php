<?php
declare(strict_types=1);

namespace AI\SeoContent\Api\Data;

/**
 * SEO generation queue message data interface.
 */
interface SeoGenerationMessageInterface
{
    public const JOB_ID = 'job_id';
    public const PRODUCT_ID = 'product_id';
    public const STORE_ID = 'store_id';

    /**
     * Get job ID.
     *
     * @return int
     */
    public function getJobId(): int;

    /**
     * Set job ID.
     *
     * @param int $jobId
     * @return $this
     */
    public function setJobId(int $jobId): self;

    /**
     * Get product ID.
     *
     * @return int
     */
    public function getProductId(): int;

    /**
     * Set product ID.
     *
     * @param int $productId
     * @return $this
     */
    public function setProductId(int $productId): self;

    /**
     * Get store ID.
     *
     * @return int
     */
    public function getStoreId(): int;

    /**
     * Set store ID.
     *
     * @param int $storeId
     * @return $this
     */
    public function setStoreId(int $storeId): self;
}
