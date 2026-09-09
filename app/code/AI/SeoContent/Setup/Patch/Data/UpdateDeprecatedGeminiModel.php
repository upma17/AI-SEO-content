<?php
declare(strict_types=1);

namespace AI\SeoContent\Setup\Patch\Data;

use AI\SeoContent\Model\Config;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class UpdateDeprecatedGeminiModel implements DataPatchInterface
{
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup
    ) {
    }

    public function apply(): void
    {
        $this->moduleDataSetup->getConnection()->update(
            $this->moduleDataSetup->getTable('core_config_data'),
            ['value' => Config::DEFAULT_GEMINI_MODEL],
            [
                'path = ?' => 'ai_seo_content/llm/gemini_model',
                'value IN (?)' => [
                    'gemini-2.0-flash',
                    'gemini-2.0-flash-lite',
                    'gemini-2.5-flash',
                    'gemini-2.5-flash-lite',
                    'gemini-3.6-flash',
                    'gemini/gemini-2.0-flash',
                    'gemini/gemini-3.6-flash',
                ],
            ]
        );
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
