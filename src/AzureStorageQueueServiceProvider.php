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
        // Use callAfterResolving so the driver is registered even if QueueManager
        // was already resolved before this provider booted.
        $this->callAfterResolving(QueueManager::class, static function (QueueManager $manager): void {
            // extend() registers a connector factory: a zero-argument closure that
            // returns a ConnectorInterface. QueueManager calls it with no arguments
            // and then calls ->connect($config) on the result itself.
            $manager->extend('azure-storage-queue', static function (): AzureStorageQueueConnector {
                return new AzureStorageQueueConnector;
            });
        });
    }
}
