<?php

declare(strict_types=1);

namespace Marmanik\AzureStorageQueue\Commands;

use Illuminate\Console\Command;
use Marmanik\AzureStorageQueue\AzureQueueClientAdapter;

/**
 * Artisan command that creates the Azure Storage Queue for a given connection.
 *
 * Usage:
 *   php artisan azure-storage-queue:create-queue
 *   php artisan azure-storage-queue:create-queue --connection=azure
 */
class CreateQueueCommand extends Command
{
    protected $signature = 'azure-storage-queue:create-queue
                            {--connection=azure : The queue connection name from config/queue.php}';

    protected $description = 'Create the Azure Storage Queue for the specified connection';

    public function handle(): int
    {
        $connection = (string) $this->option('connection');

        /** @var array<string, mixed>|null $config */
        $config = config("queue.connections.{$connection}");

        if (! is_array($config) || ($config['driver'] ?? '') !== 'azure-storage-queue') {
            $this->error("Connection [{$connection}] is not an azure-storage-queue connection.");

            return self::FAILURE;
        }

        $queueName = (string) $config['queue'];

        $this->info("Creating queue [{$queueName}] on connection [{$connection}]…");

        $client = new AzureQueueClientAdapter($config);
        $client->createQueue($queueName);

        $this->info("Queue [{$queueName}] is ready.");

        return self::SUCCESS;
    }
}
