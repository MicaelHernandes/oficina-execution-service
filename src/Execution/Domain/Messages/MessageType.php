<?php

namespace Domain\Execution\Domain\Messages;

/**
 * Contrato das mensagens da saga da OS do lado do Execution (espelho do
 * MessageType do OS Service). Consome os comandos `execution.*` do tópico do
 * OS Service e publica os eventos `execution.*` no próprio tópico.
 */
enum MessageType: string
{
    // ── Comandos recebidos do OS Service (orquestrador) ──────────────
    case EnqueueExecution = 'execution.job.enqueue';
    case CancelExecution = 'execution.job.cancel';

    // ── Eventos publicados para o OS Service ─────────────────────────
    case ExecutionStarted = 'execution.job.started';
    case ExecutionFinished = 'execution.job.finished';
    case ExecutionFailed = 'execution.job.failed';
}
