<?php
declare(strict_types=1);

namespace AI\SeoContent\Model\Llm;

use AI\SeoContent\Logger\Logger;
use Magento\Framework\Serialize\Serializer\Json;

class ResponseParser
{
    public function __construct(
        private readonly Json $json,
        private readonly LlmErrorContext $errorContext,
        private readonly Logger $logger
    ) {
    }

    /**
     * @return array{
     *     meta_title: string,
     *     meta_description: string,
     *     meta_keyword?: string,
     *     short_description?: string
     * }|null
     */
    public function parse(
        string $text,
        string $providerLabel,
        bool $includeMetaKeyword,
        bool $includeShortDescription
    ): ?array {
        $text = trim($text);
        if ($text === '') {
            $message = $providerLabel . ' returned empty content.';
            $this->errorContext->set($message);
            $this->logger->error($message);
            return null;
        }

        // Strip markdown code fences if present.
        if (str_starts_with($text, '```')) {
            $text = preg_replace('/^```(?:json)?\s*/i', '', $text) ?? $text;
            $text = preg_replace('/\s*```$/', '', $text) ?? $text;
            $text = trim($text);
        }

        try {
            $parsed = $this->json->unserialize($text);
        } catch (\InvalidArgumentException $e) {
            $extracted = $this->extractJsonObject($text);
            if ($extracted === null) {
                $message = $providerLabel . ' response is not valid JSON.';
                $this->errorContext->set($message);
                $this->logger->error($message, [
                    'text' => mb_substr($text, 0, 300),
                ]);
                return null;
            }

            try {
                $parsed = $this->json->unserialize($extracted);
            } catch (\InvalidArgumentException $inner) {
                $message = $providerLabel . ' response is not valid JSON.';
                $this->errorContext->set($message);
                $this->logger->error($message, [
                    'text' => mb_substr($text, 0, 300),
                ]);
                return null;
            }
        }

        if (!is_array($parsed)) {
            $message = $providerLabel . ' response is not a JSON object.';
            $this->errorContext->set($message);
            $this->logger->error($message);
            return null;
        }

        $metaTitle = trim((string) ($parsed['meta_title'] ?? ''));
        $metaDescription = trim((string) ($parsed['meta_description'] ?? ''));

        if ($metaTitle === '' || $metaDescription === '') {
            $message = $providerLabel . ' JSON missing meta_title or meta_description.';
            $this->errorContext->set($message);
            $this->logger->error($message, [
                'parsed' => $parsed,
            ]);
            return null;
        }

        $result = [
            'meta_title' => mb_substr($metaTitle, 0, 60),
            'meta_description' => mb_substr($metaDescription, 0, 160),
        ];

        if ($includeMetaKeyword) {
            $metaKeyword = trim((string) ($parsed['meta_keyword'] ?? ''));
            if ($metaKeyword !== '') {
                $result['meta_keyword'] = mb_substr($metaKeyword, 0, 255);
            }
        }

        if ($includeShortDescription) {
            $shortDescription = trim((string) ($parsed['short_description'] ?? ''));
            if ($shortDescription !== '') {
                $result['short_description'] = mb_substr($shortDescription, 0, 500);
            }
        }

        return $result;
    }

    private function extractJsonObject(string $text): ?string
    {
        if (preg_match('/\{.*\}/s', $text, $matches) === 1) {
            return $matches[0];
        }

        return null;
    }
}
