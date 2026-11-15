<?php

namespace Domain\Execution\Presentation\Commands;

use Domain\Execution\Application\MessageConsumer;
use Illuminate\Console\Command;

/** Consumidor da fila SQS do Execution Service (Deployment oficina-execution-worker). */
class ConsumeMessagesCommand extends Command
{
    protected $signature = 'execution:consume
        {--once : Processa um único lote e sai}
        {--max-time=3600 : Encerra após N segundos (o Deployment reinicia o processo)}';

    protected $description = 'Consome os comandos da saga (OS Service) destinados ao Execution';

    private bool $shouldStop = false;

    public function handle(MessageConsumer $consumer): int
    {
        $this->trap([SIGTERM, SIGINT], function (): void {
            $this->shouldStop = true;
        });
        $deadline = time() + (int) $this->option('max-time');

        do {
            $result = $consumer->consumeBatch();

            if ($result['processed'] + $result['failed'] > 0) {
                $this->line("processadas: {$result['processed']} | com falha: {$result['failed']}");
            }
        } while (! $this->option('once') && ! $this->shouldStop && time() < $deadline);

        return self::SUCCESS;
    }
}
