<?php

namespace Domain\Execution\Domain\Ports;

/** Mensagem recebida da fila, ainda não confirmada (ack). */
final class ReceivedMessage
{
    public function __construct(
        public readonly string $body,
        public readonly string $receipt,
    ) {}
}
