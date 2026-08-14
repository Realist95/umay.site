<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

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
}
