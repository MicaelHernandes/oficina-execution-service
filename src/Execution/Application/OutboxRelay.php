<?php

namespace Domain\Execution\Application;

use Domain\Execution\Domain\Ports\MessagePublisher;
use Domain\Execution\Domain\Repositories\OutboxInterface;
use Illuminate\Support\Facades\Log;

/**
 * Publica no broker as mensagens pendentes do outbox, na ordem em que foram
 * gravadas. Falhas ficam registradas na linha e são tentadas de novo no
 * próximo ciclo (entrega "pelo menos uma vez"; o consumidor é idempotente).
 */
class OutboxRelay
{
    public function __construct(
        private readonly OutboxInterface $outbox,
        private readonly MessagePublisher $publisher,
    ) {}

    /** @return int quantidade publicada neste ciclo */
    public function relay(int $batchSize = 50): int
    {
        $published = 0;

        foreach ($this->outbox->pending($batchSize) as $rowId => $envelope) {
            try {
                $this->publisher->publish($envelope);
                $this->outbox->markPublished($rowId);
                $published++;
                Log::info('mensagem publicada', $envelope->logContext());
            } catch (\Throwable $e) {
                $this->outbox->markFailed($rowId, $e->getMessage());
                Log::error('falha ao publicar mensagem', $envelope->logContext() + ['error' => $e->getMessage()]);

                // Preserva a ordem: não publica as seguintes antes desta.
                break;
            }
        }

        return $published;
    }
}
