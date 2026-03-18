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
            // The closure MUST be static: QueueManager::extend() binds closures
            // to itself, so a non-static closure would lose $this (the provider).
            $manager->extend('azure-storage-queue', static function (array $config): AzureStorageQueue {
                return (new AzureStorageQueueConnector)->connect($config);
            });
        });
    }
}
