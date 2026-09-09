<?php
declare(strict_types=1);

namespace AI\SeoContent\Model\Llm;

interface LlmClientInterface
{
    /**
     * @return array{
     *     meta_title: string,
     *     meta_description: string,
     *     meta_keyword?: string,
     *     short_description?: string
     * }|null
     */
    public function generateSeoContent(
        string $prompt,
        bool $includeMetaKeyword = true,
        bool $includeShortDescription = true
    ): ?array;
}
