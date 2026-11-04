<?php

namespace Domain\Execution\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $os_id
 * @property string|null $saga_id
 * @property string|null $correlation_id
 * @property string $status
 * @property int $priority
 * @property int|null $vehicle_id
 * @property int|null $mechanic_user_id
 * @property string|null $complaint
 * @property array<int, array<string, mixed>> $services
 * @property array<int, array<string, mixed>> $parts
 * @property string|null $failure_reason
 * @property \DateTimeImmutable $enqueued_at
 * @property \DateTimeImmutable|null $started_at
 * @property \DateTimeImmutable|null $finished_at
 */
class ExecutionJobModel extends Model
{
    protected $table = 'execution_jobs';

    protected $fillable = [
        'os_id',
        'saga_id',
        'correlation_id',
        'status',
        'priority',
        'vehicle_id',
        'mechanic_user_id',
        'complaint',
        'services',
        'parts',
        'failure_reason',
        'enqueued_at',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'services' => 'array',
            'parts' => 'array',
            'enqueued_at' => 'immutable_datetime',
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
        ];
    }

    public function events(): HasMany
    {
        return $this->hasMany(ExecutionJobEventModel::class, 'job_id')->orderBy('id');
    }
}
