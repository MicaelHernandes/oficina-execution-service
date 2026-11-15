<?php

use Domain\Execution\Domain\Messages\Envelope;
use Domain\Execution\Domain\Messages\MessageType;
use Domain\Execution\Domain\Ports\MessagePublisher;
use Domain\Execution\Domain\Ports\MessageSource;
use Domain\Execution\Domain\Ports\ReceivedMessage;
use Domain\Execution\Domain\Repositories\ExecutionJobRepositoryInterface;
use Domain\Execution\Infrastructure\Models\ExecutionJobModel;
use Domain\Execution\Infrastructure\Models\OutboxMessageModel;

class FakeSource implements MessageSource
{
    public array $acked = [];

    public function __construct(public array $queue = []) {}

    public function receive(): array
    {
        [$batch, $this->queue] = [$this->queue, []];

        return $batch;
    }

    public function ack(ReceivedMessage $message): void
    {
        $this->acked[] = $message->receipt;
    }
}

class FakePublisher implements MessagePublisher
{
    public array $published = [];

    public function __construct(public bool $fail = false) {}

    public function publish(Envelope $envelope): void
    {
        if ($this->fail) {
            throw new RuntimeException('SNS indisponível');
        }
        $this->published[] = $envelope->type;
    }
}

it('execution:consume processa, descarta malformadas e mantém as que falham', function () {
    $source = new FakeSource([
        new ReceivedMessage(json_encode(['Type' => 'Notification', 'Message' => enqueueCommand(10)->toJson()]), 'r-ok'),
        new ReceivedMessage('lixo', 'r-malformed'),
        new ReceivedMessage(enqueueCommand(11)->toJson(), 'r-fail'),
    ]);
    app()->instance(MessageSource::class, $source);

    // Banco instável só para a OS 11.
    $real = app(ExecutionJobRepositoryInterface::class);
    $this->mock(ExecutionJobRepositoryInterface::class, function ($mock) use ($real) {
        $mock->shouldReceive('findActiveByOsId')->andReturnUsing(fn ($os, $lock) => $real->findActiveByOsId($os, $lock));
        $mock->shouldReceive('save')->andReturnUsing(function ($job) use ($real) {
            if ($job->getOsId() === 11) {
                throw new RuntimeException('deadlock');
            }

            return $real->save($job);
        });
    });

    $this->artisan('execution:consume', ['--once' => true])
        ->expectsOutput('processadas: 1 | com falha: 1')
        ->assertSuccessful();

    expect($source->acked)->toBe(['r-ok', 'r-malformed'])
        ->and(ExecutionJobModel::where('os_id', 10)->exists())->toBeTrue()
        ->and(ExecutionJobModel::where('os_id', 11)->exists())->toBeFalse();
});

it('execution:relay publica o outbox em ordem e para no primeiro erro', function () {
    $id = enqueued();
    $this->withToken('test-staff-token')->postJson("/execution/api/jobs/{$id}/start-diagnosis");
    $this->withToken('test-staff-token')->postJson("/execution/api/jobs/{$id}/fail", ['reason' => 'x']);

    app()->instance(MessagePublisher::class, new FakePublisher(fail: true));
    $this->artisan('execution:relay', ['--once' => true])->assertSuccessful();
    expect(OutboxMessageModel::whereNull('published_at')->count())->toBe(2)
        ->and(OutboxMessageModel::orderBy('id')->first()->last_error)->toBe('SNS indisponível');

    app()->instance(MessagePublisher::class, $publisher = new FakePublisher);
    $this->artisan('execution:relay', ['--once' => true])
        ->expectsOutput('publicadas: 2')
        ->assertSuccessful();

    expect($publisher->published)->toBe([MessageType::ExecutionStarted->value, MessageType::ExecutionFailed->value])
        ->and(OutboxMessageModel::whereNull('published_at')->count())->toBe(0);
});

it('expõe métricas da fila e do outbox', function () {
    $id = enqueued(osId: 1);
    enqueued(osId: 2);
    $token = ['Authorization' => 'Bearer test-staff-token'];
    $this->postJson("/execution/api/jobs/{$id}/start-diagnosis", [], $token);
    $this->postJson("/execution/api/jobs/{$id}/start-repair", [], $token);
    $this->postJson("/execution/api/jobs/{$id}/finish", [], $token);

    $this->get('/metrics')
        ->assertOk()
        ->assertSee('oficina_execution_jobs{status="queued"} 1', false)
        ->assertSee('oficina_execution_jobs{status="finished"} 1', false)
        ->assertSee('oficina_execution_avg_execution_seconds', false)
        ->assertSee('oficina_execution_outbox_pending_messages 2', false);
});
