<?php
declare(strict_types=1);

namespace AI\SeoContent\Model\Config\Source;

use AI\SeoContent\Model\Config;
use Magento\Framework\Data\OptionSourceInterface;

class GeminiModel implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            [
                'value' => Config::DEFAULT_GEMINI_MODEL,
                'label' => __('Gemini 3.5 Flash Lite (default)'),
            ],
            [
                'value' => 'gemini-3.5-flash',
                'label' => __('Gemini 3.5 Flash'),
            ],
            [
                'value' => 'gemini-3.6-flash',
                'label' => __('Gemini 3.6 Flash'),
            ],
            [
                'value' => 'gemini-2.5-flash-lite',
                'label' => __('Gemini 2.5 Flash Lite'),
            ],
        ];
    }
}
