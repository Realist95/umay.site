<?php

namespace App\Services\AI;

interface LlmProviderInterface
{
    public function generate(ChatRequest $request): ChatResponse;
}
