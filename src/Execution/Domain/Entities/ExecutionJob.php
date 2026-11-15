<?php

namespace Domain\Execution\Domain\Entities;

use Domain\Execution\Domain\Enums\JobStatus;

/**
 * Execução de uma OS na oficina. Criada pelo comando execution.job.enqueue
 * da saga; avança pelas mãos do mecânico (diagnóstico → reparo → fim) e cada
 * passo fica na linha do tempo.
 */
class ExecutionJob
{
    /** @var array<int, array{status: string, note: ?string, at: string}> novos eventos da linha do tempo */
    private array $pendingTimeline = [];

    /**
     * @param  array<int, array<string, mixed>>  $services
     * @param  array<int, array<string, mixed>>  $parts
     */
    public function __construct(
        private readonly ?int $id,
        private readonly int $osId,
        private readonly ?string $sagaId,
        private readonly ?string $correlationId,
        private JobStatus $status,
        private readonly int $priority,
        private readonly ?int $vehicleId,
        private ?int $mechanicUserId,
        private readonly ?string $complaint,
        private readonly array $services,
        private readonly array $parts,
        private readonly \DateTimeImmutable $enqueuedAt,
        private ?\DateTimeImmutable $startedAt = null,
        private ?\DateTimeImmutable $finishedAt = null,
        private ?string $failureReason = null,
    ) {}

    /** @param array<string, mixed> $payload payload do comando execution.job.enqueue */
    public static function enqueue(int $osId, ?string $sagaId, ?string $correlationId, array $payload): self
    {
        $services = $payload['services'] ?? [];
        $parts = $payload['parts'] ?? [];

        $job = new self(
            id: null,
            osId: $osId,
            sagaId: $sagaId,
            correlationId: $correlationId,
            status: JobStatus::Queued,
            priority: (int) ($payload['priority'] ?? 0),
            vehicleId: isset($payload['vehicle_id']) ? (int) $payload['vehicle_id'] : null,
            mechanicUserId: isset($payload['mechanic_user_id']) ? (int) $payload['mechanic_user_id'] : null,
            complaint: $payload['complaint'] ?? null,
            services: is_array($services) ? $services : [],
            parts: is_array($parts) ? $parts : [],
            enqueuedAt: new \DateTimeImmutable,
        );
        $job->record(JobStatus::Queued, 'OS paga; aguardando mecânico');

        return $job;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOsId(): int
    {
        return $this->osId;
    }

    public function getSagaId(): ?string
    {
        return $this->sagaId;
    }

    public function getCorrelationId(): ?string
    {
        return $this->correlationId;
    }

    public function getStatus(): JobStatus
    {
        return $this->status;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function getVehicleId(): ?int
    {
        return $this->vehicleId;
    }

    public function getMechanicUserId(): ?int
    {
        return $this->mechanicUserId;
    }

    public function getComplaint(): ?string
    {
        return $this->complaint;
    }

    /** @return array<int, array<string, mixed>> */
    public function getServices(): array
    {
        return $this->services;
    }

    /** @return array<int, array<string, mixed>> */
    public function getParts(): array
    {
        return $this->parts;
    }

    public function getEnqueuedAt(): \DateTimeImmutable
    {
        return $this->enqueuedAt;
    }

    public function getStartedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getFinishedAt(): ?\DateTimeImmutable
    {
        return $this->finishedAt;
    }

    public function getFailureReason(): ?string
    {
        return $this->failureReason;
    }

    /** Mecânico assume a OS e começa o diagnóstico. */
    public function startDiagnosis(?int $mechanicUserId, ?string $note): void
    {
        $this->moveTo(JobStatus::Diagnosing, $note ?? 'diagnóstico iniciado');
        $this->mechanicUserId = $mechanicUserId ?? $this->mechanicUserId;
        $this->startedAt = new \DateTimeImmutable;
    }

    public function startRepair(?string $note): void
    {
        $this->moveTo(JobStatus::Repairing, $note ?? 'reparo iniciado');
    }

    public function finish(?string $note): void
    {
        $this->moveTo(JobStatus::Finished, $note ?? 'execução finalizada');
        $this->finishedAt = new \DateTimeImmutable;
    }

    public function fail(string $reason): void
    {
        $this->moveTo(JobStatus::Failed, $reason);
        $this->failureReason = $reason;
        $this->finishedAt = new \DateTimeImmutable;
    }

    public function cancel(string $reason): void
    {
        $this->moveTo(JobStatus::Cancelled, $reason);
    }

    /** Anotação do mecânico sem mudar de etapa (ex.: peça trocada, teste feito). */
    public function addNote(string $note): void
    {
        $this->record($this->status, $note);
    }

    /** @return array<int, array{status: string, note: ?string, at: string}> */
    public function pullTimeline(): array
    {
        [$events, $this->pendingTimeline] = [$this->pendingTimeline, []];

        return $events;
    }

    private function moveTo(JobStatus $target, ?string $note): void
    {
        $this->status->guardTransitionTo($target);
        $this->status = $target;
        $this->record($target, $note);
    }

    private function record(JobStatus $status, ?string $note): void
    {
        $this->pendingTimeline[] = [
            'status' => $status->value,
            'note' => $note,
            'at' => (new \DateTimeImmutable)->format(DATE_ATOM),
        ];
    }
}
