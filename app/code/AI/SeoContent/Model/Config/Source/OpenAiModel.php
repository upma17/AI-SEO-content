<?php
declare(strict_types=1);

namespace AI\SeoContent\Model\Config\Source;

use AI\SeoContent\Model\Config;
use Magento\Framework\Data\OptionSourceInterface;

class OpenAiModel implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            [
                'value' => Config::DEFAULT_OPENAI_MODEL,
                'label' => __('GPT-4o Mini (default)'),
            ],
            [
                'value' => 'gpt-4o',
                'label' => __('GPT-4o'),
            ],
            [
                'value' => 'gpt-4.1-mini',
                'label' => __('GPT-4.1 Mini'),
            ],
        ];
    }
}
