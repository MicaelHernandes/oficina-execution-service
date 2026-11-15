<?php

namespace Domain\Execution\Domain\Repositories;

use Domain\Execution\Domain\Messages\Envelope;

/**
 * Transactional outbox: a mensagem é gravada na mesma transação da mudança de
 * estado e publicada depois pelo `saga:relay`. Assim nenhum comando sai sem o
 * commit, e nenhum commit fica sem o seu comando.
 */
interface OutboxInterface
{
    public function add(Envelope $envelope): void;

    /** @return array<int, Envelope> mensagens pendentes, indexadas pelo id da linha */
    public function pending(int $limit): array;

    public function markPublished(int $rowId): void;

    public function markFailed(int $rowId, string $error): void;
}
