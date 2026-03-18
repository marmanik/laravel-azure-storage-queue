<?php

declare(strict_types=1);

namespace Marmanik\AzureStorageQueue;

use Illuminate\Queue\Connectors\ConnectorInterface;
use Marmanik\AzureStorageQueue\Contracts\AzureQueueClient;

/**
 * Instantiates AzureStorageQueue from the connection config array.
 *
 * Laravel's QueueManager calls connect() with the 'azure' block from
 * config/queue.php and expects a Queue implementation in return.
 */
class AzureStorageQueueConnector implements ConnectorInterface
{
    public function connect(array $config): AzureStorageQueue
    {
        $client = $this->buildClient($config);

        return new AzureStorageQueue(
            client: $client,
            default: $config['queue'],
            visibilityTimeout: (int) ($config['timeout'] ?? 60),
        );
    }

    protected function buildClient(array $config): AzureQueueClient
    {
        return new AzureQueueClientAdapter($config);
    }
}
