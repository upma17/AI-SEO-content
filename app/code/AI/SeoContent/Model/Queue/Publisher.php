<?php
declare(strict_types=1);

namespace AI\SeoContent\Model\Queue;

use AI\SeoContent\Api\Data\JobInterface;
use AI\SeoContent\Api\Data\SeoGenerationMessageInterface;
use AI\SeoContent\Api\Data\SeoGenerationMessageInterfaceFactory;
use AI\SeoContent\Model\Config;
use AI\SeoContent\Model\JobTracker;
use Magento\Framework\MessageQueue\PublisherInterface;

class Publisher
{
    public function __construct(
        private readonly PublisherInterface $publisher,
        private readonly SeoGenerationMessageInterfaceFactory $messageFactory,
        private readonly JobTracker $jobTracker,
        private readonly Config $config
    ) {
    }

    public function publish(int $productId, int $storeId, ?string $sku = null): void
    {
        $job = $this->jobTracker->createPendingJob($productId, $storeId, $sku);
        $this->publishJob($job);
    }

    public function republishJob(JobInterface $job): void
    {
        $this->publishJob($job);
    }

    private function publishJob(JobInterface $job): void
    {
        /** @var SeoGenerationMessageInterface $message */
        $message = $this->messageFactory->create();
        $message->setJobId((int) $job->getJobId());
        $message->setProductId($job->getProductId());
        $message->setStoreId($job->getStoreId());

        $topic = $this->config->getActiveTopic($job->getStoreId());
        $this->publisher->publish($topic, $message);
    }
}
