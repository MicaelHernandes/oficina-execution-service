<?php

namespace Domain\Execution\Domain\Repositories;

use Domain\Execution\Domain\Entities\ExecutionJob;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ExecutionJobRepositoryInterface
{
    /** Com `$lock`, trava a linha até o fim da transação (SELECT ... FOR UPDATE). */
    public function findById(int $id, bool $lock = false): ?ExecutionJob;

    public function findActiveByOsId(int $osId, bool $lock = false): ?ExecutionJob;

    public function findBySagaId(string $sagaId): ?ExecutionJob;

    /** Fila: maior prioridade primeiro, depois a mais antiga. */
    public function queue(?string $status, int $perPage): LengthAwarePaginator;

    /** Grava o job e os novos eventos da linha do tempo. */
    public function save(ExecutionJob $job): ExecutionJob;

    /** @return array<int, array{status: string, note: ?string, at: string}> */
    public function timeline(int $jobId): array;
}
