<?php
declare(strict_types=1);

namespace AI\SeoContent\Api\Data;

interface JobInterface
{
    public const JOB_ID = 'job_id';
    public const PRODUCT_ID = 'product_id';
    public const STORE_ID = 'store_id';
    public const SKU = 'sku';
    public const STATUS = 'status';
    public const QUEUE_CONNECTION = 'queue_connection';
    public const ERROR_MESSAGE = 'error_message';
    public const RESULT_SUMMARY = 'result_summary';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    public function getJobId(): ?int;

    public function setJobId(int $jobId): self;

    public function getProductId(): int;

    public function setProductId(int $productId): self;

    public function getStoreId(): int;

    public function setStoreId(int $storeId): self;

    public function getSku(): ?string;

    public function setSku(?string $sku): self;

    public function getStatus(): string;

    public function setStatus(string $status): self;

    public function getQueueConnection(): string;

    public function setQueueConnection(string $connection): self;

    public function getErrorMessage(): ?string;

    public function setErrorMessage(?string $message): self;

    public function getResultSummary(): ?string;

    public function setResultSummary(?string $summary): self;

    public function getCreatedAt(): ?string;

    public function getUpdatedAt(): ?string;
}
