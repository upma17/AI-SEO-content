<?php
declare(strict_types=1);

namespace AI\SeoContent\Model\Queue\Consumer;

use AI\SeoContent\Api\Data\SeoGenerationMessageInterface;
use AI\SeoContent\Logger\Logger;
use AI\SeoContent\Model\JobTracker;
use AI\SeoContent\Model\ProductSeoGenerator;

class SeoGenerationConsumer
{
    public function __construct(
        private readonly ProductSeoGenerator $productSeoGenerator,
        private readonly JobTracker $jobTracker,
        private readonly Logger $logger
    ) {
    }

    public function process(SeoGenerationMessageInterface $message): void
    {
        $productId = $message->getProductId();
        $storeId = $message->getStoreId();
        $jobId = $message->getJobId();

        if ($productId <= 0 || $storeId <= 0) {
            $this->logger->error('Queue message has invalid product_id or store_id.');
            if ($jobId > 0) {
                $this->jobTracker->markFailed($jobId, 'Invalid product_id or store_id in queue message.');
            }
            return;
        }

        if ($jobId > 0) {
            $this->jobTracker->markProcessing($jobId);
        }

        $result = $this->productSeoGenerator->processProduct($productId, $storeId, true);
        $summary = $this->productSeoGenerator->getLastProcessSummary();

        if ($jobId <= 0) {
            return;
        }

        switch ($result) {
            case ProductSeoGenerator::RESULT_SAVED:
                $this->jobTracker->markCompleted($jobId, $summary);
                break;
            case ProductSeoGenerator::RESULT_SKIPPED:
                $this->jobTracker->markSkipped($jobId, $summary ?: 'Skipped by processing rules or dry run.');
                break;
            default:
                $this->jobTracker->markFailed(
                    $jobId,
                    $summary ?: 'SEO generation failed. See var/log/ai_seo_content.log.'
                );
                $this->logger->error(sprintf(
                    'Queue consumer failed for job_id=%d product_id=%d store_id=%d',
                    $jobId,
                    $productId,
                    $storeId
                ));
        }
    }
}
