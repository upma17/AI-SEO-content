<?php
declare(strict_types=1);

namespace AI\SeoContent\Model\Llm;

use AI\SeoContent\Logger\Logger;
use AI\SeoContent\Model\Config;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;

class ClaudeClient implements LlmClientInterface
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION = '2023-06-01';

    public function __construct(
        private readonly Config $config,
        private readonly Curl $curl,
        private readonly Json $json,
        private readonly ResponseParser $responseParser,
        private readonly LlmErrorContext $errorContext,
        private readonly Logger $logger
    ) {
    }

    public function generateSeoContent(
        string $prompt,
        bool $includeMetaKeyword = true,
        bool $includeShortDescription = true
    ): ?array {
        $this->errorContext->clear();

        $apiKey = $this->config->getAnthropicApiKey();
        if ($apiKey === '') {
            $this->errorContext->set('Anthropic API key is not configured in admin.');
            $this->logger->error('Anthropic API key is not configured.');
            return null;
        }

        $payload = [
            'model' => $this->config->getAnthropicModel(),
            'max_tokens' => 1024,
            'system' => 'You are an e-commerce SEO assistant. Respond with valid JSON only, no markdown.',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
            'temperature' => $this->config->getTemperature(),
        ];

        try {
            $this->curl->setHeaders([
                'Content-Type' => 'application/json',
                'x-api-key' => $apiKey,
                'anthropic-version' => self::API_VERSION,
            ]);
            $this->curl->setTimeout(90);
            $this->curl->post(self::API_URL, $this->json->serialize($payload));

            $status = $this->curl->getStatus();
            $body = $this->curl->getBody();

            if ($status < 200 || $status >= 300) {
                $message = $this->extractApiErrorMessage($body) ?? ('HTTP ' . $status);
                $this->errorContext->set('Anthropic API error: ' . $message);
                $this->logger->error('Anthropic API error', [
                    'status' => $status,
                    'body' => mb_substr($body, 0, 500),
                ]);
                return null;
            }

            $response = $this->json->unserialize($body);
            $text = '';
            foreach ($response['content'] ?? [] as $block) {
                if (($block['type'] ?? '') === 'text') {
                    $text .= (string) ($block['text'] ?? '');
                }
            }

            return $this->responseParser->parse(
                $text,
                'Claude',
                $includeMetaKeyword,
                $includeShortDescription
            );
        } catch (\Throwable $e) {
            $this->errorContext->set('Anthropic request failed: ' . $e->getMessage());
            $this->logger->error('Anthropic request failed: ' . $e->getMessage());
            return null;
        }
    }

    private function extractApiErrorMessage(string $body): ?string
    {
        try {
            $decoded = $this->json->unserialize($body);
            $message = trim((string) ($decoded['error']['message'] ?? ''));
            return $message !== '' ? $message : null;
        } catch (\InvalidArgumentException $e) {
            return null;
        }
    }
}
