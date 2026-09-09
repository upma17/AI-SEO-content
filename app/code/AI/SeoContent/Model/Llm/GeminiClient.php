<?php
declare(strict_types=1);

namespace AI\SeoContent\Model\Llm;

use AI\SeoContent\Logger\Logger;
use AI\SeoContent\Model\Config;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Serialize\Serializer\Json;

class GeminiClient implements LlmClientInterface
{
    private const API_URL = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';

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

        $apiKey = $this->config->getGeminiApiKey();
        if ($apiKey === '') {
            $this->errorContext->set('Gemini API key is not configured in admin.');
            $this->logger->error('Gemini API key is not configured.');
            return null;
        }

        $model = $this->config->getGeminiModel();
        $url = sprintf(self::API_URL, rawurlencode($model)) . '?key=' . rawurlencode($apiKey);

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => $this->config->getTemperature(),
                'responseMimeType' => 'application/json',
            ],
        ];

        try {
            $response = $this->postWithRateLimitRetry($url, $this->json->serialize($payload));
            $status = $response['status'];
            $body = $response['body'];

            if ($status < 200 || $status >= 300) {
                $message = $this->extractApiErrorMessage($body) ?? ('HTTP ' . $status);
                $this->errorContext->set('Gemini API error: ' . $message);
                $this->logger->error('Gemini API error', [
                    'status' => $status,
                    'body' => mb_substr($body, 0, 500),
                ]);
                return null;
            }

            $decoded = $this->json->unserialize($body);
            $text = $this->extractResponseText($decoded);
            if ($text === '') {
                $blockReason = $decoded['promptFeedback']['blockReason'] ?? null;
                $finishReason = $decoded['candidates'][0]['finishReason'] ?? null;
                $details = $blockReason
                    ? 'Prompt blocked: ' . $blockReason
                    : ($finishReason ? 'Generation stopped: ' . $finishReason : 'Empty response from Gemini.');
                $this->errorContext->set($details);
                $this->logger->error('Gemini returned no usable text.', [
                    'blockReason' => $blockReason,
                    'finishReason' => $finishReason,
                ]);
                return null;
            }

            $parsed = $this->responseParser->parse(
                $text,
                'Gemini',
                $includeMetaKeyword,
                $includeShortDescription
            );
            if ($parsed === null) {
                $this->errorContext->set(
                    $this->errorContext->get() ?? 'Gemini response could not be parsed as SEO JSON.'
                );
            }

            return $parsed;
        } catch (\Throwable $e) {
            $this->errorContext->set('Gemini request failed: ' . $e->getMessage());
            $this->logger->error('Gemini request failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * @return array{status: int, body: string}
     */
    private function postWithRateLimitRetry(string $url, string $payload): array
    {
        $this->curl->setHeaders(['Content-Type' => 'application/json']);
        $this->curl->setTimeout(90);

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->curl->post($url, $payload);
            $status = $this->curl->getStatus();
            $body = $this->curl->getBody();

            if ($status === 429 && $attempt === 0) {
                $retrySeconds = $this->parseRetryDelaySeconds($body) ?? 40;
                $retrySeconds = min(max($retrySeconds, 1), 120);
                $this->logger->warning(sprintf(
                    'Gemini rate limit hit; waiting %d seconds before retry.',
                    $retrySeconds
                ));
                sleep($retrySeconds);
                continue;
            }

            return ['status' => $status, 'body' => $body];
        }

        return ['status' => $this->curl->getStatus(), 'body' => $this->curl->getBody()];
    }

    private function extractResponseText(array $response): string
    {
        $parts = $response['candidates'][0]['content']['parts'] ?? [];
        $text = '';
        foreach ($parts as $part) {
            if (isset($part['text'])) {
                $text .= (string) $part['text'];
            }
        }

        return trim($text);
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

    private function parseRetryDelaySeconds(string $body): ?int
    {
        if (preg_match('/retry in ([0-9]+(?:\.[0-9]+)?)s/i', $body, $matches) === 1) {
            return (int) ceil((float) $matches[1]);
        }

        return null;
    }
}
