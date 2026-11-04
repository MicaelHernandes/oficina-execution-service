<?php

namespace Domain\Execution\Domain\Enums;

use Domain\Execution\Domain\Exceptions\DomainException;

/**
 * Ciclo de vida da execução de uma OS:
 *   queued → diagnosing → repairing → finished
 * Falha (failed) a partir de qualquer etapa ativa dispara a compensação da
 * saga no OS Service; cancelled vem do próprio orquestrador.
 */
enum JobStatus: string
{
    case Queued = 'queued';
    case Diagnosing = 'diagnosing';
    case Repairing = 'repairing';
    case Finished = 'finished';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Na fila',
            self::Diagnosing => 'Em diagnóstico',
            self::Repairing => 'Em reparo',
            self::Finished => 'Finalizada',
            self::Failed => 'Falhou',
            self::Cancelled => 'Cancelada',
        };
    }

    /** @return JobStatus[] */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Queued => [self::Diagnosing, self::Failed, self::Cancelled],
            self::Diagnosing => [self::Repairing, self::Failed, self::Cancelled],
            self::Repairing => [self::Finished, self::Failed, self::Cancelled],
            self::Finished, self::Failed, self::Cancelled => [],
        };
    }

    public function guardTransitionTo(self $target): void
    {
        if (! in_array($target, $this->allowedTransitions(), true)) {
            throw DomainException::because("Transição inválida de '{$this->label()}' para '{$target->label()}'.");
        }
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Queued, self::Diagnosing, self::Repairing], true);
    }
}
