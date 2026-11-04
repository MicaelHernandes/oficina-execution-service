<?php

namespace Domain\Execution\Infrastructure\Messaging;

use Domain\Execution\Domain\Messages\Envelope;
use Domain\Execution\Domain\Ports\MessagePublisher;
use Illuminate\Support\Facades\Log;

/** Desenvolvimento local: só registra a mensagem no log. */
class LogMessagePublisher implements MessagePublisher
{
    public function publish(Envelope $envelope): void
    {
        Log::info('mensagem (driver log)', $envelope->toArray());
    }
}
