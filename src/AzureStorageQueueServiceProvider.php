<?php

declare(strict_types=1);

namespace Marmanik\AzureStorageQueue;

use Illuminate\Queue\QueueManager;
use Illuminate\Support\ServiceProvider;
use Marmanik\AzureStorageQueue\Commands\CreateQueueCommand;

class AzureStorageQueueServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerQueueDriver();

        if ($this->app->runningInConsole()) {
            $this->commands([CreateQueueCommand::class]);
        }
    }

    private function registerQueueDriver(): void
    {
        // Use resolving() so the connector is registered whenever QueueManager
        // is resolved — same pattern used by mongodb/laravel-mongodb.
        // addConnector() stores a zero-argument factory that returns a
        // ConnectorInterface; QueueManager calls it and then ->connect($config).
        $this->app->resolving('queue', function (QueueManager $manager): void {
            $manager->addConnector('azure-storage-queue', static function (): AzureStorageQueueConnector {
                return new AzureStorageQueueConnector;
            });
        });
    }
}
