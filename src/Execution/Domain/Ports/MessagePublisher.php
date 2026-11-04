<?php

namespace Domain\Execution\Domain\Ports;

use Domain\Execution\Domain\Messages\Envelope;

interface MessagePublisher
{
    public function publish(Envelope $envelope): void;
}
