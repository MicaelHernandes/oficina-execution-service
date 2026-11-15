<?php

use Domain\Execution\Domain\Entities\ExecutionJob;
use Domain\Execution\Domain\Enums\JobStatus;
use Domain\Execution\Domain\Exceptions\DomainException;
use Domain\Execution\Domain\Exceptions\NotFoundException;

function aJob(): ExecutionJob
{
    return ExecutionJob::enqueue(7, 's', 'c', ['complaint' => 'Barulho', 'services' => 'invalido', 'vehicle_id' => '4']);
}

it('nasce na fila com a linha do tempo inicial', function () {
    $job = aJob();

    expect($job->getStatus())->toBe(JobStatus::Queued)
        ->and($job->getServices())->toBe([])
        ->and($job->getVehicleId())->toBe(4)
        ->and($job->getMechanicUserId())->toBeNull()
        ->and($job->pullTimeline()[0]['status'])->toBe('queued')
        ->and($job->pullTimeline())->toBe([]);
});

it('percorre as etapas e guarda datas e motivo da falha', function () {
    $job = aJob();
    $job->startDiagnosis(5, null);
    expect($job->getStartedAt())->not->toBeNull()->and($job->getMechanicUserId())->toBe(5);

    $job->startRepair('ok');
    $job->fail('peça errada');

    expect($job->getStatus())->toBe(JobStatus::Failed)
        ->and($job->getFailureReason())->toBe('peça errada')
        ->and($job->getFinishedAt())->not->toBeNull()
        ->and(array_column($job->pullTimeline(), 'status'))->toBe(['queued', 'diagnosing', 'repairing', 'failed']);
});

it('respeita a máquina de estados', function () {
    expect(fn () => aJob()->finish(null))->toThrow(DomainException::class)
        ->and(JobStatus::Finished->allowedTransitions())->toBe([])
        ->and(JobStatus::Cancelled->isActive())->toBeFalse()
        ->and(JobStatus::Repairing->isActive())->toBeTrue()
        ->and(array_map(fn ($s) => $s->label(), JobStatus::cases()))->toHaveCount(6)
        ->and(NotFoundException::forEntity('Execução', 3)->getMessage())->toBe("Execução '3' não encontrado.");
});
