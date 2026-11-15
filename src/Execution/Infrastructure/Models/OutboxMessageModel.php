<?php

namespace Domain\Execution\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $message_id
 * @property string $type
 * @property string|null $saga_id
 * @property array<string, mixed> $body
 * @property int $attempts
 * @property string|null $last_error
 * @property \DateTimeImmutable|null $published_at
 */
class OutboxMessageModel extends Model
{
    protected $table = 'outbox_messages';

    protected $fillable = [
        'message_id',
        'type',
        'saga_id',
        'body',
        'attempts',
        'last_error',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'body' => 'array',
            'published_at' => 'immutable_datetime',
        ];
    }
}
