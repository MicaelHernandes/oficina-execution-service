<?php

namespace Domain\Execution\Infrastructure\Repositories;

use Domain\Execution\Domain\Entities\ExecutionJob;
use Domain\Execution\Domain\Enums\JobStatus;
use Domain\Execution\Domain\Repositories\ExecutionJobRepositoryInterface;
use Domain\Execution\Infrastructure\Models\ExecutionJobEventModel;
use Domain\Execution\Infrastructure\Models\ExecutionJobModel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class EloquentExecutionJobRepository implements ExecutionJobRepositoryInterface
{
    public function findById(int $id, bool $lock = false): ?ExecutionJob
    {
        $query = ExecutionJobModel::whereKey($id);
        if ($lock) {
            $query->lockForUpdate();
        }

        $model = $query->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function findActiveByOsId(int $osId, bool $lock = false): ?ExecutionJob
    {
        $active = array_map(
            fn (JobStatus $s) => $s->value,
            array_filter(JobStatus::cases(), fn (JobStatus $s) => $s->isActive()),
        );

        $query = ExecutionJobModel::where('os_id', $osId)->whereIn('status', $active)->latest('id');
        if ($lock) {
            $query->lockForUpdate();
        }

        $model = $query->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function findBySagaId(string $sagaId): ?ExecutionJob
    {
        $model = ExecutionJobModel::where('saga_id', $sagaId)->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function queue(?string $status, int $perPage): LengthAwarePaginator
    {
        $query = ExecutionJobModel::query()
            ->orderByDesc('priority')
            ->orderBy('enqueued_at')
            ->orderBy('id');

        $status !== null
            ? $query->where('status', $status)
            : $query->whereIn('status', [JobStatus::Queued->value, JobStatus::Diagnosing->value, JobStatus::Repairing->value]);

        return $query->paginate($perPage)->through(fn (ExecutionJobModel $m) => $this->toEntity($m));
    }

    public function save(ExecutionJob $job): ExecutionJob
    {
        $data = [
            'os_id' => $job->getOsId(),
            'saga_id' => $job->getSagaId(),
            'correlation_id' => $job->getCorrelationId(),
            'status' => $job->getStatus()->value,
            'priority' => $job->getPriority(),
            'vehicle_id' => $job->getVehicleId(),
            'mechanic_user_id' => $job->getMechanicUserId(),
            'complaint' => $job->getComplaint(),
            'services' => $job->getServices(),
            'parts' => $job->getParts(),
            'failure_reason' => $job->getFailureReason(),
            'enqueued_at' => $job->getEnqueuedAt(),
            'started_at' => $job->getStartedAt(),
            'finished_at' => $job->getFinishedAt(),
        ];

        $model = $job->getId() !== null
            ? tap(ExecutionJobModel::findOrFail($job->getId()))->update($data)
            : ExecutionJobModel::create($data);

        foreach ($job->pullTimeline() as $event) {
            ExecutionJobEventModel::create([
                'job_id' => $model->id,
                'status' => $event['status'],
                'note' => $event['note'],
                'occurred_at' => $event['at'],
            ]);
        }

        return $this->toEntity($model->fresh());
    }

    public function timeline(int $jobId): array
    {
        return ExecutionJobEventModel::where('job_id', $jobId)->orderBy('id')->get()
            ->map(fn (ExecutionJobEventModel $e) => [
                'status' => $e->status,
                'note' => $e->note,
                'at' => $e->occurred_at->format(DATE_ATOM),
            ])->all();
    }

    private function toEntity(ExecutionJobModel $model): ExecutionJob
    {
        return new ExecutionJob(
            id: $model->id,
            osId: (int) $model->os_id,
            sagaId: $model->saga_id,
            correlationId: $model->correlation_id,
            status: JobStatus::from($model->status),
            priority: (int) $model->priority,
            vehicleId: $model->vehicle_id !== null ? (int) $model->vehicle_id : null,
            mechanicUserId: $model->mechanic_user_id !== null ? (int) $model->mechanic_user_id : null,
            complaint: $model->complaint,
            services: $model->services ?? [],
            parts: $model->parts ?? [],
            enqueuedAt: $model->enqueued_at,
            startedAt: $model->started_at,
            finishedAt: $model->finished_at,
            failureReason: $model->failure_reason,
        );
    }
}
