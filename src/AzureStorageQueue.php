<?php

declare(strict_types=1);

namespace Marmanik\AzureStorageQueue;

use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Queue\Queue;
use Marmanik\AzureStorageQueue\Contracts\AzureQueueClient;
use Marmanik\AzureStorageQueue\Data\QueueMessage;
use Marmanik\AzureStorageQueue\Exceptions\AzureQueueException;

/**
 * Laravel Queue implementation backed by Azure Storage Queues.
 *
 * Wire this up in config/queue.php — see README for the full example.
 */
class AzureStorageQueue extends Queue implements QueueContract
{
    /**
     * Maximum storage size of a single Azure Storage Queue message (bytes).
     * Azure enforces 64 KiB on the raw (stored) text.
     */
    private const MAX_BYTES = 65536;

    public function __construct(
        protected AzureQueueClient $client,
        /** Default queue name used when none is provided by the caller. */
        protected string $default,
        /** Visibility timeout in seconds applied to listMessages() calls. */
        protected int $visibilityTimeout = 60,
    ) {}

    // -------------------------------------------------------------------------
    // Queue contract
    // -------------------------------------------------------------------------

    public function size($queue = null): int
    {
        return $this->client->getApproximateMessageCount($this->getQueue($queue));
    }

    public function push($job, $data = '', $queue = null): mixed
    {
        return $this->pushRaw(
            $this->createPayload($job, $this->getQueue($queue), $data),
            $queue,
        );
    }

    public function pushRaw($payload, $queue = null, array $options = []): mixed
    {
        $ttl = isset($options['ttl']) ? (int) $options['ttl'] : null;

        $this->client->createMessage(
            $this->getQueue($queue),
            $this->encodePayload($payload),
            null,
            $ttl,
        );

        return null;
    }

    public function later($delay, $job, $data = '', $queue = null): mixed
    {
        $seconds = $this->secondsUntil($delay);

        $this->client->createMessage(
            $this->getQueue($queue),
            $this->encodePayload($this->createPayload($job, $this->getQueue($queue), $data)),
            $seconds,   // initial visibility timeout = the delay
        );

        return null;
    }

    public function pop($queue = null): ?JobContract
    {
        $queueName = $this->getQueue($queue);

        $messages = $this->client->listMessages($queueName, 1, $this->visibilityTimeout);

        if (empty($messages)) {
            return null;
        }

        /** @var QueueMessage $sdkMessage */
        $sdkMessage = $messages[0];

        // Keep the original encoded body so AzureStorageQueueJob can pass it
        // back unchanged to updateMessage() on release().
        $encodedBody = $sdkMessage->body;
        $decodedBody = $this->decodePayload($encodedBody);

        $message = new QueueMessage(
            messageId: $sdkMessage->messageId,
            popReceipt: $sdkMessage->popReceipt,
            body: $decodedBody,
            dequeueCount: $sdkMessage->dequeueCount,
        );

        return new AzureStorageQueueJob(
            container: $this->container,
            client: $this->client,
            message: $message,
            encodedBody: $encodedBody,
            connectionName: $this->connectionName,
            queue: $queueName,
        );
    }

    // -------------------------------------------------------------------------
    // Payload encoding / decoding
    // -------------------------------------------------------------------------

    /**
     * Base64-encode the payload for Azure Storage.
     * If the result exceeds 64 KiB, try gzcompress before giving up.
     *
     * The compressed variant is prefixed with "gz:" so decodePayload() can
     * detect and reverse the compression transparently.
     */
    public function encodePayload(string $payload): string
    {
        $encoded = base64_encode($payload);

        if (strlen($encoded) <= self::MAX_BYTES) {
            return $encoded;
        }

        $compressed = 'gz:'.base64_encode((string) gzcompress($payload));

        if (strlen($compressed) > self::MAX_BYTES) {
            throw AzureQueueException::payloadTooLarge();
        }

        return $compressed;
    }

    /**
     * Reverse encodePayload(): detect the "gz:" prefix and decompress if needed.
     */
    public function decodePayload(string $body): string
    {
        if (str_starts_with($body, 'gz:')) {
            return (string) gzuncompress((string) base64_decode(substr($body, 3)));
        }

        return (string) base64_decode($body);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    protected function getQueue(?string $queue): string
    {
        return $queue ?? $this->default;
    }
}
