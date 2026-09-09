<?php
declare(strict_types=1);

namespace AI\SeoContent\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    public const PROVIDER_GEMINI = 'gemini';
    public const PROVIDER_OPENAI = 'openai';
    public const PROVIDER_ANTHROPIC = 'anthropic';

    public const DEFAULT_PROVIDER = self::PROVIDER_GEMINI;
    public const DEFAULT_GEMINI_MODEL = 'gemini-3.5-flash-lite';
    public const DEFAULT_OPENAI_MODEL = 'gpt-4o-mini';
    public const DEFAULT_ANTHROPIC_MODEL = 'claude-3-5-haiku-latest';

    public const QUEUE_CONNECTION_DB = 'db';
    public const QUEUE_CONNECTION_AMQP = 'amqp';

    public const TOPIC_DB = 'ai.seo.content.generate';
    public const TOPIC_AMQP = 'ai.seo.content.generate.amqp';

    public const CONSUMER_DB = 'aiSeoContentGenerate';
    public const CONSUMER_AMQP = 'aiSeoContentGenerateAmqp';

    private const XML_PATH_ENABLED = 'ai_seo_content/general/enabled';
    private const XML_PATH_LLM_PROVIDER = 'ai_seo_content/llm/llm_provider';
    private const XML_PATH_GEMINI_API_KEY = 'ai_seo_content/llm/gemini_api_key';
    private const XML_PATH_GEMINI_MODEL = 'ai_seo_content/llm/gemini_model';
    private const XML_PATH_OPENAI_API_KEY = 'ai_seo_content/llm/openai_api_key';
    private const XML_PATH_OPENAI_MODEL = 'ai_seo_content/llm/openai_model';
    private const XML_PATH_ANTHROPIC_API_KEY = 'ai_seo_content/llm/anthropic_api_key';
    private const XML_PATH_ANTHROPIC_MODEL = 'ai_seo_content/llm/anthropic_model';
    private const XML_PATH_TEMPERATURE = 'ai_seo_content/llm/temperature';
    /** @deprecated Legacy path — migrated to gemini_api_key */
    private const XML_PATH_LEGACY_API_KEY = 'ai_seo_content/general/api_key';
    /** @deprecated Legacy path — migrated to gemini_model */
    private const XML_PATH_LEGACY_MODEL = 'ai_seo_content/general/model';
    private const XML_PATH_BATCH_SIZE = 'ai_seo_content/general/batch_size';
    private const XML_PATH_ENQUEUE_BATCH_SIZE = 'ai_seo_content/general/enqueue_batch_size';
    private const XML_PATH_USE_MESSAGE_QUEUE = 'ai_seo_content/general/use_message_queue';
    private const XML_PATH_QUEUE_CONNECTION = 'ai_seo_content/general/queue_connection';
    private const XML_PATH_DRY_RUN = 'ai_seo_content/general/dry_run';
    private const XML_PATH_ONLY_EMPTY_META = 'ai_seo_content/general/only_empty_meta';
    private const XML_PATH_GENERATE_META_KEYWORD = 'ai_seo_content/general/generate_meta_keyword';
    private const XML_PATH_GENERATE_SHORT_DESCRIPTION = 'ai_seo_content/general/generate_short_description';
    private const XML_PATH_ONLY_EMPTY_SHORT_DESCRIPTION = 'ai_seo_content/general/only_empty_short_description';
    private const XML_PATH_REQUEST_DELAY_MS = 'ai_seo_content/general/request_delay_ms';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly EncryptorInterface $encryptor
    ) {
    }

    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getProvider(): string
    {
        $value = (string) $this->scopeConfig->getValue(self::XML_PATH_LLM_PROVIDER);

        return match ($value) {
            self::PROVIDER_OPENAI, self::PROVIDER_ANTHROPIC => $value,
            default => self::PROVIDER_GEMINI,
        };
    }

    public function getProviderLabel(): string
    {
        return match ($this->getProvider()) {
            self::PROVIDER_OPENAI => 'OpenAI',
            self::PROVIDER_ANTHROPIC => 'Claude',
            default => 'Gemini',
        };
    }

    public function getActiveModel(): string
    {
        return match ($this->getProvider()) {
            self::PROVIDER_OPENAI => $this->getOpenAiModel(),
            self::PROVIDER_ANTHROPIC => $this->getAnthropicModel(),
            default => $this->getGeminiModel(),
        };
    }

    public function getGeminiApiKey(): string
    {
        $key = $this->decryptConfigValue(self::XML_PATH_GEMINI_API_KEY);
        if ($key !== '') {
            return $key;
        }

        return $this->decryptConfigValue(self::XML_PATH_LEGACY_API_KEY);
    }

    public function getOpenAiApiKey(): string
    {
        return $this->decryptConfigValue(self::XML_PATH_OPENAI_API_KEY);
    }

    public function getAnthropicApiKey(): string
    {
        return $this->decryptConfigValue(self::XML_PATH_ANTHROPIC_API_KEY);
    }

    public function getGeminiModel(): string
    {
        $model = (string) $this->scopeConfig->getValue(self::XML_PATH_GEMINI_MODEL);
        if ($model === '') {
            $model = (string) $this->scopeConfig->getValue(self::XML_PATH_LEGACY_MODEL);
        }

        $model = $this->normalizeGeminiModelId($model);

        if ($model === '' || $this->isDeprecatedGeminiModel($model)) {
            return self::DEFAULT_GEMINI_MODEL;
        }

        return $model;
    }

    private function normalizeGeminiModelId(string $model): string
    {
        $model = trim($model);
        if (str_starts_with($model, 'gemini/')) {
            $model = substr($model, strlen('gemini/'));
        }

        return trim($model);
    }

    private function isDeprecatedGeminiModel(string $model): bool
    {
        $model = $this->normalizeGeminiModelId($model);

        return in_array($model, [
            'gemini-2.0-flash',
            'gemini-2.0-flash-lite',
            'gemini-2.0-flash-001',
            'gemini-2.0-flash-lite-001',
            'gemini-2.5-flash',
            'gemini-2.5-flash-lite',
            'gemini-3.6-flash',
        ], true);
    }

    public function getOpenAiModel(): string
    {
        $model = (string) $this->scopeConfig->getValue(self::XML_PATH_OPENAI_MODEL);
        return $model !== '' ? $model : self::DEFAULT_OPENAI_MODEL;
    }

    public function getAnthropicModel(): string
    {
        $model = (string) $this->scopeConfig->getValue(self::XML_PATH_ANTHROPIC_MODEL);
        return $model !== '' ? $model : self::DEFAULT_ANTHROPIC_MODEL;
    }

    /**
     * @deprecated Use getActiveModel() or provider-specific getters.
     */
    public function getModel(): string
    {
        return $this->getActiveModel();
    }

    /**
     * @deprecated Use provider-specific getters.
     */
    public function getApiKey(): string
    {
        return match ($this->getProvider()) {
            self::PROVIDER_OPENAI => $this->getOpenAiApiKey(),
            self::PROVIDER_ANTHROPIC => $this->getAnthropicApiKey(),
            default => $this->getGeminiApiKey(),
        };
    }

    public function getBatchSize(?int $storeId = null): int
    {
        $size = (int) $this->scopeConfig->getValue(
            self::XML_PATH_BATCH_SIZE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return max(1, $size ?: 10);
    }

    public function getEnqueueBatchSize(?int $storeId = null): int
    {
        $size = (int) $this->scopeConfig->getValue(
            self::XML_PATH_ENQUEUE_BATCH_SIZE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return max(1, $size ?: 500);
    }

    public function useMessageQueue(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_USE_MESSAGE_QUEUE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getQueueConnection(?int $storeId = null): string
    {
        $value = (string) $this->scopeConfig->getValue(
            self::XML_PATH_QUEUE_CONNECTION,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $value === self::QUEUE_CONNECTION_AMQP
            ? self::QUEUE_CONNECTION_AMQP
            : self::QUEUE_CONNECTION_DB;
    }

    public function getActiveTopic(?int $storeId = null): string
    {
        return $this->getQueueConnection($storeId) === self::QUEUE_CONNECTION_AMQP
            ? self::TOPIC_AMQP
            : self::TOPIC_DB;
    }

    public function getActiveConsumerName(?int $storeId = null): string
    {
        return $this->getQueueConnection($storeId) === self::QUEUE_CONNECTION_AMQP
            ? self::CONSUMER_AMQP
            : self::CONSUMER_DB;
    }

    public function isAmqp(?int $storeId = null): bool
    {
        return $this->getQueueConnection($storeId) === self::QUEUE_CONNECTION_AMQP;
    }

    public function isDryRun(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_DRY_RUN,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function onlyEmptyMeta(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ONLY_EMPTY_META,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function generateMetaKeyword(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_GENERATE_META_KEYWORD,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function generateShortDescription(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_GENERATE_SHORT_DESCRIPTION,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function onlyEmptyShortDescription(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ONLY_EMPTY_SHORT_DESCRIPTION,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getRequestDelayMs(?int $storeId = null): int
    {
        $delay = (int) $this->scopeConfig->getValue(
            self::XML_PATH_REQUEST_DELAY_MS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return max(0, $delay ?: 3000);
    }

    public function getTemperature(): float
    {
        $value = $this->scopeConfig->getValue(self::XML_PATH_TEMPERATURE);

        return $value !== null && $value !== '' ? (float) $value : 0.4;
    }

    private function decryptConfigValue(string $path): string
    {
        $encrypted = (string) $this->scopeConfig->getValue($path);
        if ($encrypted === '') {
            return '';
        }

        return $this->encryptor->decrypt($encrypted);
    }
}
