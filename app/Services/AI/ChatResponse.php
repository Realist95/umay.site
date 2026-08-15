<?php

namespace App\Services\AI;

final readonly class ChatResponse
{
    public function __construct(
        public string $text,
        public string $model,
        public int $inputTokens,
        public int $outputTokens,
        public int $latencyMs,
        public ?string $requestId,
    ) {}
}
