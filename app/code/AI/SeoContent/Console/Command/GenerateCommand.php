<?php
declare(strict_types=1);

namespace AI\SeoContent\Console\Command;

use AI\SeoContent\Model\Config;
use AI\SeoContent\Model\ProductSeoGenerator;
use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateCommand extends Command
{
    private const OPTION_STORE = 'store';
    private const OPTION_FORCE = 'force';
    private const OPTION_SYNC = 'sync';
    private const OPTION_ENQUEUE = 'enqueue';

    public function __construct(
        private readonly ProductSeoGenerator $productSeoGenerator,
        private readonly Config $config
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('ai:seo:generate')
            ->setDescription('Generate SEO content for catalog products using Gemini AI.')
            ->addOption(
                self::OPTION_STORE,
                null,
                InputOption::VALUE_OPTIONAL,
                'Store ID (default: all store views)'
            )
            ->addOption(
                self::OPTION_FORCE,
                'f',
                InputOption::VALUE_NONE,
                'Run even if module is disabled in admin'
            )
            ->addOption(
                self::OPTION_SYNC,
                null,
                InputOption::VALUE_NONE,
                'Process synchronously (ignore message queue setting)'
            )
            ->addOption(
                self::OPTION_ENQUEUE,
                null,
                InputOption::VALUE_NONE,
                'Enqueue products to message queue without processing'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $storeOption = $input->getOption(self::OPTION_STORE);
        $storeId = $storeOption !== null ? (int) $storeOption : null;
        $force = (bool) $input->getOption(self::OPTION_FORCE);
        $sync = (bool) $input->getOption(self::OPTION_SYNC);
        $enqueueOnly = (bool) $input->getOption(self::OPTION_ENQUEUE);

        if ($enqueueOnly) {
            $output->writeln('<info>Enqueuing products to message queue...</info>');
            $stats = $this->productSeoGenerator->enqueueBatch($storeId, $force);
            $output->writeln(sprintf(
                'Enqueued. processed=%d enqueued=%d failed=%d',
                $stats['processed'],
                $stats['enqueued'],
                $stats['failed']
            ));
            $output->writeln('<comment>Start consumer: bin/magento ai:seo:consumer:start</comment>');
            return $stats['failed'] > 0 ? Cli::RETURN_FAILURE : Cli::RETURN_SUCCESS;
        }

        $output->writeln('<info>AI SEO Content Generator — starting batch...</info>');

        $stats = $sync
            ? $this->productSeoGenerator->processBatchSync($storeId, $force)
            : $this->productSeoGenerator->processBatch($storeId, $force);

        if (isset($stats['enqueued']) && $stats['enqueued'] > 0) {
            $output->writeln(sprintf(
                'Done (enqueue mode). processed=%d enqueued=%d failed=%d',
                $stats['processed'],
                $stats['enqueued'],
                $stats['failed']
            ));
            $output->writeln('<comment>Start consumer: bin/magento ai:seo:consumer:start</comment>');
        } else {
            $output->writeln(sprintf(
                'Done. processed=%d saved=%d skipped=%d failed=%d',
                $stats['processed'],
                $stats['saved'],
                $stats['skipped'],
                $stats['failed']
            ));

            if ($stats['processed'] === 0 && !$force && !$this->isEnabledForAnyStore($storeId)) {
                $output->writeln(
                    '<comment>No products processed: module is disabled. Use --force or enable in '
                    . 'Stores → Configuration → AI → SEO Content Generator.</comment>'
                );
            } elseif ($stats['processed'] === 0) {
                $output->writeln(
                    '<comment>No eligible products found. Check "Only Empty Meta Description" and other '
                    . 'filters in admin, or run with --sync --force after clearing filters.</comment>'
                );
            } elseif ($stats['failed'] > 0 && $stats['saved'] === 0) {
                $output->writeln(
                    '<error>All products failed. Check var/log/ai_seo_content.log — common cause is an '
                    . 'invalid or retired Gemini model.</error>'
                );
            } elseif (
                !$sync
                && !$enqueueOnly
                && $this->usesQueueForAnyStore($storeId)
                && ($stats['saved'] ?? 0) === 0
            ) {
                $output->writeln(
                    '<comment>Message queue is enabled: products are enqueued, not processed inline. '
                    . 'Run bin/magento ai:seo:consumer:start or use --sync.</comment>'
                );
            }
        }

        $output->writeln('<comment>Check var/log/ai_seo_content.log for details.</comment>');

        return $stats['failed'] > 0 ? Cli::RETURN_FAILURE : Cli::RETURN_SUCCESS;
    }

    private function isEnabledForAnyStore(?int $storeId): bool
    {
        return $this->config->isEnabled($storeId ?? 1);
    }

    private function usesQueueForAnyStore(?int $storeId): bool
    {
        if ($storeId !== null) {
            return $this->config->useMessageQueue($storeId);
        }

        return $this->config->useMessageQueue(1);
    }
}
