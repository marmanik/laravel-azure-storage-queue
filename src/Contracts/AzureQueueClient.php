<?php

declare(strict_types=1);

namespace Marmanik\AzureStorageQueue\Contracts;

use Marmanik\AzureStorageQueue\Data\QueueMessage;

interface AzureQueueClient
{
    /**
     * Send a message to the queue.
     *
     * @param  int|null  $visibilityTimeout  Seconds before the message becomes visible (initial delay for later()).
     * @param  int|null  $messageTtl  Seconds the message lives in the queue (max 604800 = 7 days).
     */
    public function createMessage(
        string $queue,
        string $body,
        ?int $visibilityTimeout = null,
        ?int $messageTtl = null,
    ): void;

    /**
     * Receive up to $numberOfMessages messages from the queue.
     *
     * Received messages are hidden for $visibilityTimeout seconds.
     *
     * @return QueueMessage[]
     */
    public function listMessages(string $queue, int $numberOfMessages, int $visibilityTimeout): array;

    /**
     * Permanently delete a received message.
     */
    public function deleteMessage(string $queue, string $messageId, string $popReceipt): void;

    /**
     * Update a received message's body and reset its visibility timeout (implements release()).
     */
    public function updateMessage(
        string $queue,
        string $messageId,
        string $popReceipt,
        string $body,
        int $visibilityTimeout,
    ): void;

    /**
     * Return the approximate number of messages currently in the queue.
     */
    public function getApproximateMessageCount(string $queue): int;

    /**
     * Create the queue if it does not already exist.
     */
    public function createQueue(string $queue): void;
}
