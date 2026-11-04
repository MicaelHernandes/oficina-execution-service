<?php

namespace Domain\Execution\Application;

use Domain\Execution\Domain\Entities\ExecutionJob;
use Domain\Execution\Domain\Messages\Envelope;
use Domain\Execution\Domain\Messages\MessageType;
use Domain\Execution\Domain\Repositories\ExecutionJobRepositoryInterface;
use Domain\Execution\Domain\Repositories\InboxInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Comandos que o orquestrador (OS Service) envia ao Execution. Cada comando é
 * processado numa transação com o inbox (idempotência).
 */
class CommandHandler
{
    public function __construct(
        private readonly ExecutionJobRepositoryInterface $jobs,
        private readonly InboxInterface $inbox,
    ) {}

    public function handle(Envelope $command): void
    {
        $type = $command->messageType();

        if ($command->osId === null || ! in_array($type, [MessageType::EnqueueExecution, MessageType::CancelExecution], true)) {
            Log::info('mensagem ignorada pelo Execution', $command->logContext());

            return;
        }

        DB::transaction(function () use ($command, $type) {
            if (! $this->inbox->remember($command)) {
                Log::info('comando duplicado ignorado', $command->logContext());

                return;
            }

            match ($type) {
                MessageType::EnqueueExecution => $this->enqueue($command),
                MessageType::CancelExecution => $this->cancel($command),
            };
        });
    }

    private function enqueue(Envelope $command): void
    {
        // Uma execução ativa por OS: reenvio do mesmo pedido não duplica a fila.
        if ($this->jobs->findActiveByOsId($command->osId, lock: true) !== null) {
            Log::warning('OS já está na fila de execução', $command->logContext());

            return;
        }

        $job = $this->jobs->save(ExecutionJob::enqueue(
            $command->osId,
            $command->sagaId,
            $command->correlationId,
            $command->payload,
        ));

        Log::info('OS enfileirada para execução', $command->logContext() + ['job_id' => $job->getId()]);
    }

    /** Compensação (prazo vencido no orquestrador): tira a OS da fila. */
    private function cancel(Envelope $command): void
    {
        $job = $this->jobs->findActiveByOsId($command->osId, lock: true);

        if ($job === null) {
            return;
        }

        $job->cancel('cancelada pela saga: '.($command->payload['reason'] ?? 'sem motivo'));
        $this->jobs->save($job);

        Log::info('execução cancelada pela saga', $command->logContext() + ['job_id' => $job->getId()]);
    }
}
