<?php

namespace Domain\Execution\Infrastructure\Repositories;

use Domain\Execution\Domain\Messages\Envelope;
use Domain\Execution\Domain\Repositories\InboxInterface;
use Illuminate\Support\Facades\DB;

class DatabaseInbox implements InboxInterface
{
    public function remember(Envelope $envelope): bool
    {
        // ON CONFLICT DO NOTHING: 0 linhas inseridas = mensagem já vista.
        return DB::table('inbox_messages')->insertOrIgnore([
            'message_id' => $envelope->id,
            'type' => mb_substr($envelope->type, 0, 64),
            'source' => mb_substr($envelope->source, 0, 64),
            'processed_at' => now(),
        ]) === 1;
    }
}
