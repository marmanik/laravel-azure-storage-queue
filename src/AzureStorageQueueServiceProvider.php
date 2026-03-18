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
            // Do NOT use static here: QueueManager::extend() calls bindTo() on the
            // closure to rebind it to the QueueManager instance so $config arrives
            // correctly as a parameter. A static closure blocks bindTo() on PHP 8.2
            // and causes the closure to be invoked with 0 arguments.
            // We never reference $this inside, so rebinding is harmless.
            $manager->extend('azure-storage-queue', function (array $config): AzureStorageQueue {
                return (new AzureStorageQueueConnector)->connect($config);
            });
        });
    }
}
