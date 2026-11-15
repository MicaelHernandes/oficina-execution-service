<?php

namespace Domain\Execution\Infrastructure\Repositories;

use Domain\Execution\Domain\Messages\Envelope;
use Domain\Execution\Domain\Repositories\OutboxInterface;
use Domain\Execution\Infrastructure\Models\OutboxMessageModel;
use Illuminate\Support\Facades\DB;

class EloquentOutbox implements OutboxInterface
{
    public function add(Envelope $envelope): void
    {
        OutboxMessageModel::create([
            'message_id' => $envelope->id,
            'type' => $envelope->type,
            'saga_id' => $envelope->sagaId,
            'body' => $envelope->toArray(),
        ]);
    }

    public function pending(int $limit): array
    {
        $pending = [];

        OutboxMessageModel::whereNull('published_at')
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (OutboxMessageModel $row) use (&$pending) {
                $pending[$row->id] = Envelope::fromArray($row->body);
            });

        return $pending;
    }

    public function markPublished(int $rowId): void
    {
        OutboxMessageModel::whereKey($rowId)->update([
            'published_at' => now(),
            'attempts' => DB::raw('attempts + 1'),
            'last_error' => null,
        ]);
    }

    public function markFailed(int $rowId, string $error): void
    {
        OutboxMessageModel::whereKey($rowId)->update([
            'attempts' => DB::raw('attempts + 1'),
            'last_error' => mb_substr($error, 0, 2000),
        ]);
    }
}
