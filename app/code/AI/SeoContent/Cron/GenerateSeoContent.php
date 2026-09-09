<?php
declare(strict_types=1);

namespace AI\SeoContent\Cron;

use AI\SeoContent\Logger\Logger;
use AI\SeoContent\Model\Config;
use AI\SeoContent\Model\ProductSeoGenerator;

class GenerateSeoContent
{
    public function __construct(
        private readonly Config $config,
        private readonly ProductSeoGenerator $productSeoGenerator,
        private readonly Logger $logger
    ) {
    }

    public function execute(): void
    {
        if (!$this->config->isEnabled()) {
            $this->logger->info('Cron skipped: AI SEO Content Generator is disabled.');
            return;
        }

        $mode = $this->config->useMessageQueue() ? 'enqueue' : 'sync';
        $this->logger->info(sprintf('Cron started: AI SEO Content Generator (%s mode).', $mode));

        $stats = $this->productSeoGenerator->processBatch();

        if ($mode === 'enqueue') {
            $this->logger->info(sprintf(
                'Cron finished (enqueue). processed=%d enqueued=%d failed=%d',
                $stats['processed'],
                $stats['enqueued'],
                $stats['failed']
            ));
            return;
        }

        $this->logger->info(sprintf(
            'Cron finished (sync). processed=%d saved=%d skipped=%d failed=%d',
            $stats['processed'],
            $stats['saved'],
            $stats['skipped'],
            $stats['failed']
        ));
    }
}
