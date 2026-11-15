<?php

use Domain\Execution\Domain\Messages\Envelope;
use Domain\Execution\Domain\Messages\MessageType;
use Domain\Execution\Infrastructure\Models\ExecutionJobModel;

it('enfileira a OS com os dados do comando da saga', function () {
    $id = enqueued();
    $job = ExecutionJobModel::find($id);

    expect($job->status)->toBe('queued')
        ->and($job->saga_id)->toBe('saga-42')
        ->and($job->correlation_id)->toBe('req-42')
        ->and($job->parts)->toHaveCount(1)
        ->and($job->events()->pluck('status')->all())->toBe(['queued']);
});

it('processa o mesmo comando uma vez e não duplica a OS ativa na fila', function () {
    $command = enqueueCommand();
    dispatchCommand($command);
    dispatchCommand($command);

    // Outro pedido para a mesma OS (id de mensagem diferente) também não duplica.
    dispatchCommand(enqueueCommand());

    expect(ExecutionJobModel::where('os_id', 42)->count())->toBe(1);
});

it('cancela a execução ativa a pedido da saga', function () {
    $id = enqueued();
    dispatchCommand(command(MessageType::CancelExecution, 42, ['reason' => 'execution_ack_timeout']));

    $job = ExecutionJobModel::find($id);
    expect($job->status)->toBe('cancelled')
        ->and($job->events()->get()->last()->note)->toBe('cancelada pela saga: execution_ack_timeout');

    // Sem execução ativa: nada a cancelar.
    dispatchCommand(command(MessageType::CancelExecution, 42));
    expect(ExecutionJobModel::find($id)->status)->toBe('cancelled');
});

it('ignora mensagens sem OS ou de tipo que não é comando do Execution', function () {
    dispatchCommand(Envelope::fromArray(['id' => 'm1', 'type' => 'execution.job.enqueue', 'source' => 'os']));
    dispatchCommand(command(MessageType::ExecutionStarted));
    dispatchCommand(Envelope::fromArray(['id' => 'm2', 'type' => 'os.order.opened', 'source' => 'os', 'os_id' => 1]));

    expect(ExecutionJobModel::count())->toBe(0);
});
