<?php
declare(strict_types=1);

namespace AI\SeoContent\Model\Config\Source;

use AI\SeoContent\Model\Config;
use Magento\Framework\Data\OptionSourceInterface;

class LlmProvider implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => Config::PROVIDER_GEMINI, 'label' => __('Google Gemini')],
            ['value' => Config::PROVIDER_OPENAI, 'label' => __('OpenAI (ChatGPT)')],
            ['value' => Config::PROVIDER_ANTHROPIC, 'label' => __('Anthropic (Claude)')],
        ];
    }
}
