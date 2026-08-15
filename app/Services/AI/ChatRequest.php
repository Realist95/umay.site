<?php

namespace App\Services\AI;

final readonly class ChatRequest
{
    /**
     * @param  list<array{role: string, content: string}>  $messages
     */
    public function __construct(
        public string $systemPrompt,
        public array $messages,
        public ?string $userReference = null,
    ) {}
}
