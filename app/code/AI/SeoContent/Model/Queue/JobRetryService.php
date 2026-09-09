<?php
declare(strict_types=1);

namespace AI\SeoContent\Model\Queue;

use AI\SeoContent\Api\Data\JobInterface;
use AI\SeoContent\Model\JobTracker;

class JobRetryService
{
    public function __construct(
        private readonly JobTracker $jobTracker,
        private readonly Publisher $publisher
    ) {
    }

    public function retryJob(int $jobId): bool
    {
        $job = $this->jobTracker->resetForRetry($jobId);
        if ($job === null) {
            return false;
        }

        $this->publisher->republishJob($job);
        return true;
    }

    /**
     * @param int[] $jobIds
     */
    public function retryJobs(array $jobIds): int
    {
        $retried = 0;
        foreach ($jobIds as $jobId) {
            if ($this->retryJob((int) $jobId)) {
                $retried++;
            }
        }

        return $retried;
    }
}
