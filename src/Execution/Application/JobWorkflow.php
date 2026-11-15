<?php

namespace Domain\Execution\Application;

use Domain\Execution\Domain\Entities\ExecutionJob;
use Domain\Execution\Domain\Exceptions\NotFoundException;
use Domain\Execution\Domain\Messages\Envelope;
use Domain\Execution\Domain\Messages\MessageType;
use Domain\Execution\Domain\Repositories\ExecutionJobRepositoryInterface;
use Domain\Execution\Domain\Repositories\OutboxInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Ações do mecânico sobre a OS em execução. Mudanças que a saga precisa saber
 * (início, fim, falha) gravam o evento no outbox na mesma transação.
 */
class JobWorkflow
{
    public function __construct(
        private readonly ExecutionJobRepositoryInterface $jobs,
        private readonly OutboxInterface $outbox,
    ) {}

    public function startDiagnosis(int $jobId, ?int $mechanicUserId, ?string $note): ExecutionJob
    {
        return $this->change($jobId, function (ExecutionJob $job) use ($mechanicUserId, $note) {
            $job->startDiagnosis($mechanicUserId, $note);
            $this->emit($job, MessageType::ExecutionStarted, [
                'job_id' => $job->getId(),
                'mechanic_user_id' => $job->getMechanicUserId(),
            ]);
        });
    }

    public function startRepair(int $jobId, ?string $note): ExecutionJob
    {
        return $this->change($jobId, fn (ExecutionJob $job) => $job->startRepair($note));
    }

    public function addNote(int $jobId, string $note): ExecutionJob
    {
        return $this->change($jobId, fn (ExecutionJob $job) => $job->addNote($note));
    }

    public function finish(int $jobId, ?string $note): ExecutionJob
    {
        return $this->change($jobId, function (ExecutionJob $job) use ($note) {
            $job->finish($note);
            $this->emit($job, MessageType::ExecutionFinished, [
                'job_id' => $job->getId(),
                'duration_seconds' => $job->getFinishedAt()->getTimestamp() - $job->getStartedAt()?->getTimestamp(),
            ]);
        });
    }

    /** Falha na execução: a saga devolve as peças, estorna e cancela a OS. */
    public function fail(int $jobId, string $reason): ExecutionJob
    {
        return $this->change($jobId, function (ExecutionJob $job) use ($reason) {
            $job->fail($reason);
            $this->emit($job, MessageType::ExecutionFailed, ['job_id' => $job->getId(), 'reason' => $reason]);
        });
    }

    private function change(int $jobId, callable $action): ExecutionJob
    {
        return DB::transaction(function () use ($jobId, $action) {
            $job = $this->jobs->findById($jobId, lock: true)
                ?? throw NotFoundException::forEntity('Execução', $jobId);

            $action($job);
            $saved = $this->jobs->save($job);

            Log::info('execução atualizada', [
                'job_id' => $saved->getId(),
                'os_id' => $saved->getOsId(),
                'status' => $saved->getStatus()->value,
                'saga_id' => $saved->getSagaId(),
                'correlation_id' => $saved->getCorrelationId(),
            ]);

            return $saved;
        });
    }

    private function emit(ExecutionJob $job, MessageType $type, array $payload): void
    {
        $this->outbox->add(Envelope::new(
            type: $type,
            sagaId: $job->getSagaId(),
            correlationId: $job->getCorrelationId(),
            osId: $job->getOsId(),
            source: (string) config('messaging.source'),
            payload: $payload,
        ));
    }
}
