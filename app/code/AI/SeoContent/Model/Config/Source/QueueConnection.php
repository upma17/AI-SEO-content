<?php
declare(strict_types=1);

namespace AI\SeoContent\Model\Config\Source;

use AI\SeoContent\Model\Config;
use Magento\Framework\Data\OptionSourceInterface;

class QueueConnection implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => Config::QUEUE_CONNECTION_DB, 'label' => __('Database Queue (MySQL)')],
            ['value' => Config::QUEUE_CONNECTION_AMQP, 'label' => __('RabbitMQ (AMQP)')],
        ];
    }
}
