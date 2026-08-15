<?php

namespace App\Services;

use App\Models\Message;

class ConversationService
{
    /**
     * This method is the boundary for the LLM implementation. Applications may
     * decorate or replace this service without coupling the Telegram job to it.
     */
    public function respond(Message $message): string
    {
        throw new \LogicException('No LLM conversation provider has been configured.');
    }
}
