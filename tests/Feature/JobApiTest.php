<?php

use Domain\Execution\Domain\Messages\MessageType;
use Domain\Execution\Infrastructure\Models\ExecutionJobModel;

beforeEach(fn () => $this->withToken('test-staff-token'));

it('exige o token do mecânico', function () {
    $this->withToken('errado')->getJson('/execution/api/jobs')->assertUnauthorized();
    $this->withHeaders(['Authorization' => ''])->getJson('/execution/api/jobs')->assertUnauthorized();
});

it('lista a fila por prioridade e antiguidade, só as ativas por padrão', function () {
    $low = enqueued(osId: 1);
    $high = enqueued(osId: 2, priority: 5);
    $done = enqueued(osId: 3);
    ExecutionJobModel::whereKey($done)->update(['status' => 'finished']);

    $this->getJson('/execution/api/jobs')
        ->assertOk()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('data.0.id', $high)
        ->assertJsonPath('data.1.id', $low);

    $this->getJson('/execution/api/jobs?status=finished')->assertJsonPath('data.0.id', $done);
    $this->getJson('/execution/api/jobs?status=xyz')->assertStatus(422);
});

it('percorre diagnóstico, reparo e fim publicando os eventos da saga', function () {
    $id = enqueued();

    $this->postJson("/execution/api/jobs/{$id}/start-diagnosis", ['mechanic_user_id' => 8, 'note' => 'Iniciando'])
        ->assertOk()
        ->assertJsonPath('data.status', 'diagnosing')
        ->assertJsonPath('data.mechanic_user_id', 8);

    $started = lastOutbox(MessageType::ExecutionStarted);
    expect($started['os_id'])->toBe(42)
        ->and($started['saga_id'])->toBe('saga-42')
        ->and($started['correlation_id'])->toBe('req-42')
        ->and($started['source'])->toBe('oficina-execution-service');

    $this->postJson("/execution/api/jobs/{$id}/notes", ['note' => 'Disco empenado'])->assertOk();
    $this->postJson("/execution/api/jobs/{$id}/start-repair")->assertOk()->assertJsonPath('data.status', 'repairing');

    $this->postJson("/execution/api/jobs/{$id}/finish", ['note' => 'Teste de rodagem ok'])
        ->assertOk()
        ->assertJsonPath('data.status', 'finished')
        ->assertJsonPath('data.timeline.2.note', 'Disco empenado');

    expect(outboxTypes())->toBe(['execution.job.started', 'execution.job.finished'])
        ->and(lastOutbox(MessageType::ExecutionFinished)['payload']['duration_seconds'])->toBeInt();

    $this->getJson("/execution/api/jobs/{$id}")
        ->assertOk()
        ->assertJsonCount(5, 'data.timeline')
        ->assertJsonPath('data.timeline.4.status', 'finished');
});

it('registra a falha e avisa a saga para compensar', function () {
    $id = enqueued();
    $this->postJson("/execution/api/jobs/{$id}/start-diagnosis");

    $this->postJson("/execution/api/jobs/{$id}/fail")->assertStatus(422);
    $this->postJson("/execution/api/jobs/{$id}/fail", ['reason' => 'Elevador quebrado'])
        ->assertOk()
        ->assertJsonPath('data.status', 'failed')
        ->assertJsonPath('data.failure_reason', 'Elevador quebrado');

    expect(lastOutbox(MessageType::ExecutionFailed)['payload']['reason'])->toBe('Elevador quebrado');
});

it('recusa transições fora de ordem e execução inexistente', function () {
    $id = enqueued();

    $this->postJson("/execution/api/jobs/{$id}/finish")
        ->assertStatus(422)
        ->assertJsonPath('message', "Transição inválida de 'Na fila' para 'Finalizada'.");
    $this->postJson("/execution/api/jobs/{$id}/start-repair")->assertStatus(422);

    $this->getJson('/execution/api/jobs/999')->assertNotFound();
    $this->postJson('/execution/api/jobs/999/start-diagnosis')->assertNotFound();

    expect(outboxTypes())->toBe([]);
});
