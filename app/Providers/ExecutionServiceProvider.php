<?php

namespace App\Providers;

use Aws\Sns\SnsClient;
use Aws\Sqs\SqsClient;
use Domain\Execution\Domain\Ports\MessagePublisher;
use Domain\Execution\Domain\Ports\MessageSource;
use Domain\Execution\Domain\Repositories\ExecutionJobRepositoryInterface;
use Domain\Execution\Domain\Repositories\InboxInterface;
use Domain\Execution\Domain\Repositories\OutboxInterface;
use Domain\Execution\Infrastructure\Messaging\LogMessagePublisher;
use Domain\Execution\Infrastructure\Messaging\NullMessageSource;
use Domain\Execution\Infrastructure\Messaging\SnsMessagePublisher;
use Domain\Execution\Infrastructure\Messaging\SqsMessageSource;
use Domain\Execution\Infrastructure\Repositories\DatabaseInbox;
use Domain\Execution\Infrastructure\Repositories\EloquentExecutionJobRepository;
use Domain\Execution\Infrastructure\Repositories\EloquentOutbox;
use Domain\Execution\Presentation\Commands\ConsumeMessagesCommand;
use Domain\Execution\Presentation\Commands\RelayOutboxCommand;
use Illuminate\Support\ServiceProvider;

class ExecutionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ExecutionJobRepositoryInterface::class, EloquentExecutionJobRepository::class);
        $this->app->bind(OutboxInterface::class, EloquentOutbox::class);
        $this->app->bind(InboxInterface::class, DatabaseInbox::class);

        // Credenciais AWS pela cadeia padrão do SDK (IRSA no EKS).
        $this->app->bind(MessagePublisher::class, fn () => match (config('messaging.publisher')) {
            'sns' => new SnsMessagePublisher(
                new SnsClient(['region' => config('messaging.region'), 'version' => '2010-03-31']),
                (string) config('messaging.sns.topic_arn'),
            ),
            default => new LogMessagePublisher,
        });

        $this->app->bind(MessageSource::class, fn () => match (config('messaging.consumer')) {
            'sqs' => new SqsMessageSource(
                new SqsClient(['region' => config('messaging.region'), 'version' => '2012-11-05']),
                (string) config('messaging.sqs.queue_url'),
                (int) config('messaging.sqs.wait_seconds'),
                (int) config('messaging.sqs.max_messages'),
            ),
            default => new NullMessageSource,
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ConsumeMessagesCommand::class,
                RelayOutboxCommand::class,
            ]);
        }
    }
}
