<?php

namespace Domain\Execution\Infrastructure\Messaging;

use Aws\Sns\SnsClient;
use Domain\Execution\Domain\Messages\Envelope;
use Domain\Execution\Domain\Ports\MessagePublisher;

/**
 * Publica no tópico SNS do Execution Service. O atributo `type` permite que cada
 * fila assinante receba só as mensagens que lhe interessam (filter policy).
 */
class SnsMessagePublisher implements MessagePublisher
{
    public function __construct(
        private readonly SnsClient $client,
        private readonly string $topicArn,
    ) {}

    public function publish(Envelope $envelope): void
    {
        $this->client->publish([
            'TopicArn' => $this->topicArn,
            'Message' => $envelope->toJson(),
            'MessageAttributes' => [
                'type' => ['DataType' => 'String', 'StringValue' => $envelope->type],
                'source' => ['DataType' => 'String', 'StringValue' => $envelope->source],
                'correlation_id' => ['DataType' => 'String', 'StringValue' => $envelope->correlationId ?? $envelope->id],
            ],
        ]);
    }
}
