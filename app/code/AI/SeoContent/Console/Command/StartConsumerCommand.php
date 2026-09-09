<?php
declare(strict_types=1);

namespace AI\SeoContent\Console\Command;

use AI\SeoContent\Model\Config;
use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class StartConsumerCommand extends Command
{
    private const OPTION_MAX_MESSAGES = 'max-messages';

    protected function configure(): void
    {
        $this->setName('ai:seo:consumer:start')
            ->setDescription('Start the AI SEO queue consumer for the configured connection (db or amqp).')
            ->addOption(
                self::OPTION_MAX_MESSAGES,
                'm',
                InputOption::VALUE_OPTIONAL,
                'Max messages to process before exit',
                '1000'
            );
    }

    public function __construct(
        private readonly Config $config
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $consumer = $this->config->getActiveConsumerName();
        $connection = $this->config->getQueueConnection();
        $maxMessages = (string) $input->getOption(self::OPTION_MAX_MESSAGES);

        $output->writeln(sprintf(
            '<info>Starting consumer "%s" (%s queue)...</info>',
            $consumer,
            $connection
        ));

        if ($connection === Config::QUEUE_CONNECTION_AMQP) {
            $output->writeln('<comment>Ensure RabbitMQ is configured in app/etc/env.php</comment>');
        }

        $command = [
            PHP_BINARY,
            BP . '/bin/magento',
            'queue:consumers:start',
            $consumer,
            '--max-messages=' . $maxMessages,
        ];

        $process = new Process($command);
        $process->setTimeout(null);
        $process->run(static function (string $type, string $buffer) use ($output): void {
            $output->write($buffer);
        });

        return $process->isSuccessful() ? Cli::RETURN_SUCCESS : Cli::RETURN_FAILURE;
    }
}
