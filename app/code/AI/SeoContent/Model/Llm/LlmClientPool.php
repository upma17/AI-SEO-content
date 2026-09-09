<?php
declare(strict_types=1);

namespace AI\SeoContent\Model\Llm;

use AI\SeoContent\Model\Config;

class LlmClientPool
{
    public function __construct(
        private readonly Config $config,
        private readonly GeminiClient $geminiClient,
        private readonly OpenAiClient $openAiClient,
        private readonly ClaudeClient $claudeClient,
        private readonly LlmErrorContext $errorContext
    ) {
    }

    public function getLastError(): ?string
    {
        return $this->errorContext->get();
    }

    public function getClient(): LlmClientInterface
    {
        return match ($this->config->getProvider()) {
            Config::PROVIDER_OPENAI => $this->openAiClient,
            Config::PROVIDER_ANTHROPIC => $this->claudeClient,
            default => $this->geminiClient,
        };
    }

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
    ): ?array {
        $this->errorContext->clear();

        $result = $this->getClient()->generateSeoContent(
            $prompt,
            $includeMetaKeyword,
            $includeShortDescription
        );

        if ($result === null && $this->errorContext->get() === null) {
            $this->errorContext->set('LLM API returned no content.');
        }

        return $result;
    }
}
