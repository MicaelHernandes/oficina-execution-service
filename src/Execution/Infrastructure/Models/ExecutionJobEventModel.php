<?php

namespace Domain\Execution\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $job_id
 * @property string $status
 * @property string|null $note
 * @property \DateTimeImmutable $occurred_at
 */
class ExecutionJobEventModel extends Model
{
    public $timestamps = false;

    protected $table = 'execution_job_events';

    protected $fillable = ['job_id', 'status', 'note', 'occurred_at'];

    protected function casts(): array
    {
        return ['occurred_at' => 'immutable_datetime'];
    }
}
