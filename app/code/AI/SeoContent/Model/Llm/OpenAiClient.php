<?php
declare(strict_types=1);

namespace AI\SeoContent\Model\Llm;

use AI\SeoContent\Logger\Logger;
use AI\SeoContent\Model\Config;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;

class OpenAiClient implements LlmClientInterface
{
    private const API_URL = 'https://api.openai.com/v1/chat/completions';

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

        $apiKey = $this->config->getOpenAiApiKey();
        if ($apiKey === '') {
            $this->errorContext->set('OpenAI API key is not configured in admin.');
            $this->logger->error('OpenAI API key is not configured.');
            return null;
        }

        $payload = [
            'model' => $this->config->getOpenAiModel(),
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are an e-commerce SEO assistant. Respond with valid JSON only.',
                ],
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
            'temperature' => $this->config->getTemperature(),
            'response_format' => ['type' => 'json_object'],
        ];

        try {
            $this->curl->setHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $apiKey,
            ]);
            $this->curl->setTimeout(90);
            $this->curl->post(self::API_URL, $this->json->serialize($payload));

            $status = $this->curl->getStatus();
            $body = $this->curl->getBody();

            if ($status < 200 || $status >= 300) {
                $message = $this->extractApiErrorMessage($body) ?? ('HTTP ' . $status);
                $this->errorContext->set('OpenAI API error: ' . $message);
                $this->logger->error('OpenAI API error', [
                    'status' => $status,
                    'body' => mb_substr($body, 0, 500),
                ]);
                return null;
            }

            $response = $this->json->unserialize($body);
            $text = $response['choices'][0]['message']['content'] ?? '';

            return $this->responseParser->parse(
                $text,
                'OpenAI',
                $includeMetaKeyword,
                $includeShortDescription
            );
        } catch (\Throwable $e) {
            $this->errorContext->set('OpenAI request failed: ' . $e->getMessage());
            $this->logger->error('OpenAI request failed: ' . $e->getMessage());
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
