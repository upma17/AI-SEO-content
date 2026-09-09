<?php
declare(strict_types=1);

namespace AI\SeoContent\Model\Llm;

class LlmErrorContext
{
    private ?string $message = null;

    public function clear(): void
    {
        $this->message = null;
    }

    public function set(string $message): void
    {
        $this->message = $message;
    }

    public function get(): ?string
    {
        return $this->message;
    }
}
