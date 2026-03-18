<?php

declare(strict_types=1);

namespace Marmanik\AzureStorageQueue;

use Marmanik\AzureStorageQueue\Contracts\AzureQueueClient;
use Marmanik\AzureStorageQueue\Data\QueueMessage;
use Marmanik\AzureStorageQueue\Exceptions\AzureQueueException;
use MicrosoftAzure\Storage\Common\Exceptions\ServiceException;
use MicrosoftAzure\Storage\Queue\Models\CreateMessageOptions;
use MicrosoftAzure\Storage\Queue\Models\ListMessagesOptions;
use MicrosoftAzure\Storage\Queue\QueueRestProxy;

/**
 * Translates between the AzureQueueClient interface and the microsoft/azure-storage-queue SDK.
 *
 * All SDK types are confined to this class. ServiceException is caught here and
 * re-thrown as AzureQueueException so the rest of the package never references
 * vendor exception classes.
 */
class AzureQueueClientAdapter implements AzureQueueClient
{
    private QueueRestProxy $proxy;

    public function __construct(array $config)
    {
        $this->proxy = QueueRestProxy::createQueueService(
            $this->buildConnectionString($config),
        );
    }

    public function createMessage(
        string $queue,
        string $body,
        ?int $visibilityTimeout = null,
        ?int $messageTtl = null,
    ): void {
        try {
            $options = new CreateMessageOptions;

            if ($visibilityTimeout !== null) {
                $options->setVisibilityTimeoutInSeconds($visibilityTimeout);
            }

            if ($messageTtl !== null) {
                $options->setMessageTtl($messageTtl);
            }

            $this->proxy->createMessage($queue, $body, $options);
        } catch (ServiceException $e) {
            throw AzureQueueException::fromServiceException($e);
        }
    }

    /** @return QueueMessage[] */
    public function listMessages(string $queue, int $numberOfMessages, int $visibilityTimeout): array
    {
        try {
            $options = new ListMessagesOptions;
            $options->setNumberOfMessages($numberOfMessages);
            $options->setVisibilityTimeoutInSeconds($visibilityTimeout);

            $result = $this->proxy->listMessages($queue, $options);

            return array_map(
                static fn ($msg) => new QueueMessage(
                    messageId: $msg->getMessageId(),
                    popReceipt: $msg->getPopReceipt(),
                    body: $msg->getMessageText(),
                    dequeueCount: (int) $msg->getDequeueCount(),
                ),
                $result->getQueueMessages(),
            );
        } catch (ServiceException $e) {
            throw AzureQueueException::fromServiceException($e);
        }
    }

    public function deleteMessage(string $queue, string $messageId, string $popReceipt): void
    {
        try {
            $this->proxy->deleteMessage($queue, $messageId, $popReceipt);
        } catch (ServiceException $e) {
            throw AzureQueueException::fromServiceException($e);
        }
    }

    public function updateMessage(
        string $queue,
        string $messageId,
        string $popReceipt,
        string $body,
        int $visibilityTimeout,
    ): void {
        try {
            $this->proxy->updateMessage($queue, $messageId, $popReceipt, $body, $visibilityTimeout);
        } catch (ServiceException $e) {
            throw AzureQueueException::fromServiceException($e);
        }
    }

    public function getApproximateMessageCount(string $queue): int
    {
        try {
            $result = $this->proxy->getQueueMetadata($queue);

            return (int) $result->getApproximateMessageCount();
        } catch (ServiceException $e) {
            throw AzureQueueException::fromServiceException($e);
        }
    }

    public function createQueue(string $queue): void
    {
        try {
            $this->proxy->createQueue($queue);
        } catch (ServiceException $e) {
            // 409 Conflict means the queue already exists — that is fine.
            if ($e->getCode() === 409) {
                return;
            }
            throw AzureQueueException::fromServiceException($e);
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function buildConnectionString(array $config): string
    {
        $name = $config['account_name'];
        $key = $config['account_key'];

        if (! empty($config['endpoint'])) {
            // Custom endpoint (Azurite local dev or sovereign cloud).
            return "AccountName={$name};AccountKey={$key};QueueEndpoint={$config['endpoint']}";
        }

        return "DefaultEndpointsProtocol=https;AccountName={$name};AccountKey={$key};EndpointSuffix=core.windows.net";
    }
}
