<?php

declare(strict_types=1);

namespace Marmanik\AzureStorageQueue\Data;

/**
 * Immutable value object representing a single Azure Storage Queue message.
 *
 * `body` holds the decoded JSON job payload (ready for getRawBody()).
 * SDK types never appear outside AzureQueueClientAdapter.
 */
readonly class QueueMessage
{
    public function __construct(
        public string $messageId,
        public string $popReceipt,
        /** Decoded job payload (JSON string). */
        public string $body,
        /** Number of times this message has been dequeued. Used for attempts(). */
        public int $dequeueCount,
    ) {}
}
