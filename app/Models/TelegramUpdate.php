<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramUpdate extends Model
{
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'telegram_update_id',
        'update_type',
        'payload',
        'status',
        'incoming_message_id',
        'response_message_id',
        'processed_at',
        'error_message',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function incomingMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'incoming_message_id');
    }

    public function responseMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'response_message_id');
    }
}
