<?php
declare(strict_types=1);

namespace AI\SeoContent\Model;

use AI\SeoContent\Api\Data\JobInterface;
use AI\SeoContent\Model\ResourceModel\Job as JobResource;
use AI\SeoContent\Model\ResourceModel\Job\CollectionFactory;
use Magento\Framework\Exception\CouldNotSaveException;

class JobTracker
{
    public function __construct(
        private readonly JobFactory $jobFactory,
        private readonly JobResource $jobResource,
        private readonly CollectionFactory $collectionFactory,
        private readonly Config $config
    ) {
    }

    public function createPendingJob(int $productId, int $storeId, ?string $sku = null): JobInterface
    {
        $existing = $this->findPendingJob($productId, $storeId);
        if ($existing !== null) {
            return $existing;
        }

        /** @var JobInterface $job */
        $job = $this->jobFactory->create();
        $job->setProductId($productId);
        $job->setStoreId($storeId);
        $job->setSku($sku);
        $job->setStatus(JobInterface::STATUS_PENDING);
        $job->setQueueConnection($this->config->getQueueConnection());

        try {
            $this->jobResource->save($job);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__('Could not create SEO job: %1', $e->getMessage()));
        }

        return $job;
    }

    public function markProcessing(int $jobId): void
    {
        $this->updateStatus($jobId, JobInterface::STATUS_PROCESSING);
    }

    public function markCompleted(int $jobId, ?string $summary = null): void
    {
        $this->updateStatus($jobId, JobInterface::STATUS_COMPLETED, null, $summary);
    }

    public function markSkipped(int $jobId, ?string $summary = null): void
    {
        $this->updateStatus($jobId, JobInterface::STATUS_SKIPPED, null, $summary);
    }

    public function markFailed(int $jobId, string $errorMessage): void
    {
        $this->updateStatus($jobId, JobInterface::STATUS_FAILED, $errorMessage);
    }

    public function getById(int $jobId): ?JobInterface
    {
        /** @var JobInterface $job */
        $job = $this->jobFactory->create();
        $this->jobResource->load($job, $jobId);
        return $job->getJobId() ? $job : null;
    }

    public function resetForRetry(int $jobId): ?JobInterface
    {
        $job = $this->getById($jobId);
        if ($job === null) {
            return null;
        }

        if (!in_array($job->getStatus(), [JobInterface::STATUS_FAILED, JobInterface::STATUS_SKIPPED], true)) {
            return null;
        }

        $job->setStatus(JobInterface::STATUS_PENDING);
        $job->setErrorMessage(null);
        $job->setResultSummary(null);
        $this->jobResource->save($job);

        return $job;
    }

    /**
     * @return array{pending: int, processing: int, completed: int, failed: int, skipped: int}
     */
    public function getStatusCounts(): array
    {
        $counts = [
            'pending' => 0,
            'processing' => 0,
            'completed' => 0,
            'failed' => 0,
            'skipped' => 0,
        ];

        $connection = $this->jobResource->getConnection();
        $table = $this->jobResource->getMainTable();
        $select = $connection->select()
            ->from($table, ['status', 'cnt' => 'COUNT(*)'])
            ->group('status');

        foreach ($connection->fetchAll($select) as $row) {
            $status = (string) ($row['status'] ?? '');
            if (array_key_exists($status, $counts)) {
                $counts[$status] = (int) $row['cnt'];
            }
        }

        return $counts;
    }

    private function findPendingJob(int $productId, int $storeId): ?JobInterface
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('product_id', $productId);
        $collection->addFieldToFilter('store_id', $storeId);
        $collection->addFieldToFilter('status', [
            'in' => [JobInterface::STATUS_PENDING, JobInterface::STATUS_PROCESSING],
        ]);
        $collection->setPageSize(1);

        $job = $collection->getFirstItem();
        return $job->getId() ? $job : null;
    }

    private function updateStatus(
        int $jobId,
        string $status,
        ?string $errorMessage = null,
        ?string $summary = null
    ): void {
        $job = $this->getById($jobId);
        if ($job === null) {
            return;
        }

        $job->setStatus($status);
        if ($errorMessage !== null) {
            $job->setErrorMessage($errorMessage);
        }
        if ($summary !== null) {
            $job->setResultSummary($summary);
        }

        $this->jobResource->save($job);
    }
}
