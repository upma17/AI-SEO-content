<?php
declare(strict_types=1);

namespace AI\SeoContent\Model;

use AI\SeoContent\Api\Data\JobInterface;
use Magento\Framework\Model\AbstractModel;

class Job extends AbstractModel implements JobInterface
{
    protected function _construct(): void
    {
        $this->_init(ResourceModel\Job::class);
    }

    public function getJobId(): ?int
    {
        $id = $this->getData(self::JOB_ID);
        return $id !== null ? (int) $id : null;
    }

    public function setJobId(int $jobId): JobInterface
    {
        return $this->setData(self::JOB_ID, $jobId);
    }

    public function getProductId(): int
    {
        return (int) $this->getData(self::PRODUCT_ID);
    }

    public function setProductId(int $productId): JobInterface
    {
        return $this->setData(self::PRODUCT_ID, $productId);
    }

    public function getStoreId(): int
    {
        return (int) $this->getData(self::STORE_ID);
    }

    public function setStoreId(int $storeId): JobInterface
    {
        return $this->setData(self::STORE_ID, $storeId);
    }

    public function getSku(): ?string
    {
        $sku = $this->getData(self::SKU);
        return $sku !== null ? (string) $sku : null;
    }

    public function setSku(?string $sku): JobInterface
    {
        return $this->setData(self::SKU, $sku);
    }

    public function getStatus(): string
    {
        return (string) $this->getData(self::STATUS);
    }

    public function setStatus(string $status): JobInterface
    {
        return $this->setData(self::STATUS, $status);
    }

    public function getQueueConnection(): string
    {
        return (string) $this->getData(self::QUEUE_CONNECTION);
    }

    public function setQueueConnection(string $connection): JobInterface
    {
        return $this->setData(self::QUEUE_CONNECTION, $connection);
    }

    public function getErrorMessage(): ?string
    {
        $message = $this->getData(self::ERROR_MESSAGE);
        return $message !== null && $message !== '' ? (string) $message : null;
    }

    public function setErrorMessage(?string $message): JobInterface
    {
        return $this->setData(self::ERROR_MESSAGE, $message);
    }

    public function getResultSummary(): ?string
    {
        $summary = $this->getData(self::RESULT_SUMMARY);
        return $summary !== null && $summary !== '' ? (string) $summary : null;
    }

    public function setResultSummary(?string $summary): JobInterface
    {
        return $this->setData(self::RESULT_SUMMARY, $summary);
    }

    public function getCreatedAt(): ?string
    {
        $value = $this->getData(self::CREATED_AT);
        return $value !== null ? (string) $value : null;
    }

    public function getUpdatedAt(): ?string
    {
        $value = $this->getData(self::UPDATED_AT);
        return $value !== null ? (string) $value : null;
    }
}
