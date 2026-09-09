<?php
declare(strict_types=1);

namespace AI\SeoContent\Model\Config\Source;

use AI\SeoContent\Model\Config;
use Magento\Framework\Data\OptionSourceInterface;

class AnthropicModel implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            [
                'value' => Config::DEFAULT_ANTHROPIC_MODEL,
                'label' => __('Claude 3.5 Haiku (default)'),
            ],
            [
                'value' => 'claude-sonnet-4-20250514',
                'label' => __('Claude Sonnet 4'),
            ],
        ];
    }
}
