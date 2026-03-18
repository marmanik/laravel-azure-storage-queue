<?php

declare(strict_types=1);

namespace Marmanik\AzureStorageQueue;

use Illuminate\Container\Container;
use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Queue\Jobs\Job;
use Marmanik\AzureStorageQueue\Contracts\AzureQueueClient;
use Marmanik\AzureStorageQueue\Data\QueueMessage;

/**
 * Represents a single Azure Storage Queue message being processed by a worker.
 */
class AzureStorageQueueJob extends Job implements JobContract
{
    public function __construct(
        Container $container,
        protected AzureQueueClient $client,
        protected QueueMessage $message,
        /**
         * The original Azure-encoded body (base64 / "gz:…") received from the queue.
         * Stored here so release() can pass it back to updateMessage() without
         * re-encoding the already-decoded JSON payload.
         */
        protected string $encodedBody,
        string $connectionName,
        string $queue,
    ) {
        $this->container = $container;
        $this->connectionName = $connectionName;
        $this->queue = $queue;
    }

    // -------------------------------------------------------------------------
    // Job contract
    // -------------------------------------------------------------------------

    public function getJobId(): string
    {
        return $this->message->messageId;
    }

    public function getRawBody(): string
    {
        return $this->message->body;
    }

    /**
     * Number of times this message has been dequeued (Azure's native counter).
     */
    public function attempts(): int
    {
        return $this->message->dequeueCount;
    }

    // -------------------------------------------------------------------------
    // Lifecycle
    // -------------------------------------------------------------------------

    /**
     * Delete the message from the queue after successful processing.
     */
    public function delete(): void
    {
        $this->client->deleteMessage(
            $this->queue,
            $this->message->messageId,
            $this->message->popReceipt,
        );

        parent::delete();
    }

    /**
     * Release the message back to the queue after $delay seconds.
     *
     * Implemented via updateMessage() which resets the visibility timeout,
     * making the message available for another worker after $delay seconds.
     * The message body is unchanged.
     */
    public function release($delay = 0): void
    {
        parent::release($delay);

        $this->client->updateMessage(
            $this->queue,
            $this->message->messageId,
            $this->message->popReceipt,
            $this->encodedBody,
            max(0, (int) $delay),
        );
    }
}
