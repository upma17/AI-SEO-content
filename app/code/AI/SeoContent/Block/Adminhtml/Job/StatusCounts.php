<?php
declare(strict_types=1);

namespace AI\SeoContent\Block\Adminhtml\Job;

use AI\SeoContent\Model\Config;
use AI\SeoContent\Model\JobTracker;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;

class StatusCounts extends Template
{
    public function __construct(
        Context $context,
        private readonly JobTracker $jobTracker,
        private readonly Config $config,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return array{pending: int, processing: int, completed: int, failed: int, skipped: int}
     */
    public function getCounts(): array
    {
        return $this->jobTracker->getStatusCounts();
    }

    public function getConsumerName(): string
    {
        return $this->config->getActiveConsumerName();
    }

    public function getQueueConnection(): string
    {
        return $this->config->getQueueConnection();
    }

    public function getLlmProvider(): string
    {
        return $this->config->getProviderLabel();
    }

    public function getLlmModel(): string
    {
        return $this->config->getActiveModel();
    }
}
