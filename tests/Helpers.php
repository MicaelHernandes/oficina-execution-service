<?php

use Domain\Execution\Application\CommandHandler;
use Domain\Execution\Domain\Messages\Envelope;
use Domain\Execution\Domain\Messages\MessageType;
use Domain\Execution\Infrastructure\Models\ExecutionJobModel;
use Domain\Execution\Infrastructure\Models\OutboxMessageModel;

/** Comando do orquestrador (OS Service) para a OS. */
function command(MessageType $type, int $osId = 42, array $payload = []): Envelope
{
    return Envelope::new($type, "saga-{$osId}", "req-{$osId}", $osId, 'oficina-os-service', $payload);
}

function enqueueCommand(int $osId = 42, int $priority = 0): Envelope
{
    return command(MessageType::EnqueueExecution, $osId, [
        'vehicle_id' => 9,
        'mechanic_user_id' => 3,
        'complaint' => 'Freio rangendo',
        'priority' => $priority,
        'services' => [['service_id' => 1, 'service_name' => 'Revisão', 'quantity' => 1, 'unit_price' => 200.0]],
        'parts' => [['part_id' => 3, 'part_name' => 'Pastilha', 'quantity' => 2, 'unit_price' => 50.0]],
    ]);
}

function dispatchCommand(Envelope $command): Envelope
{
    app(CommandHandler::class)->handle($command);

    return $command;
}

/** Envia o comando de enfileirar e devolve o id da execução criada. */
function enqueued(int $osId = 42, int $priority = 0): int
{
    dispatchCommand(enqueueCommand($osId, $priority));

    return (int) ExecutionJobModel::where('os_id', $osId)->latest('id')->value('id');
}

/** @return string[] */
function outboxTypes(): array
{
    return OutboxMessageModel::orderBy('id')->pluck('type')->all();
}

function lastOutbox(MessageType $type): array
{
    return OutboxMessageModel::where('type', $type->value)->orderByDesc('id')->firstOrFail()->body;
}
