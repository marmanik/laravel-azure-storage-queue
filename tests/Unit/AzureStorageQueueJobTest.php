<?php

declare(strict_types=1);

use Marmanik\AzureStorageQueue\AzureStorageQueueJob;
use Marmanik\AzureStorageQueue\Contracts\AzureQueueClient;
use Marmanik\AzureStorageQueue\Data\QueueMessage;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function makeJob(
    AzureQueueClient $client,
    ?QueueMessage $message = null,
    string $encodedBody = '',
    string $queue = 'default',
): AzureStorageQueueJob {
    $message ??= new QueueMessage(
        messageId: 'msg-001',
        popReceipt: 'receipt-abc',
        body: '{"job":"TestJob","data":{}}',
        dequeueCount: 1,
    );

    return new AzureStorageQueueJob(
        container: app(),
        client: $client,
        message: $message,
        encodedBody: $encodedBody ?: base64_encode($message->body),
        connectionName: 'azure',
        queue: $queue,
    );
}

// ---------------------------------------------------------------------------
// Accessors
// ---------------------------------------------------------------------------

it('returns the message id', function (): void {
    $client = Mockery::mock(AzureQueueClient::class);

    $job = makeJob($client, new QueueMessage('msg-xyz', 'rcpt', '{}', 2));

    expect($job->getJobId())->toBe('msg-xyz');
});

it('returns the raw body', function (): void {
    $client = Mockery::mock(AzureQueueClient::class);
    $payload = '{"job":"SomeJob","data":{"key":"value"}}';

    $job = makeJob($client, new QueueMessage('msg-1', 'rcpt', $payload, 1));

    expect($job->getRawBody())->toBe($payload);
});

it('returns attempts from dequeue count', function (): void {
    $client = Mockery::mock(AzureQueueClient::class);

    $job = makeJob($client, new QueueMessage('msg-1', 'rcpt', '{}', 3));

    expect($job->attempts())->toBe(3);
});

// ---------------------------------------------------------------------------
// delete()
// ---------------------------------------------------------------------------

it('calls deleteMessage on delete()', function (): void {
    $client = Mockery::mock(AzureQueueClient::class);
    $client->expects('deleteMessage')
        ->with('default', 'msg-001', 'receipt-abc')
        ->once();

    $job = makeJob($client);
    $job->delete();

    expect($job->isDeleted())->toBeTrue();
});

// ---------------------------------------------------------------------------
// release()
// ---------------------------------------------------------------------------

it('calls updateMessage on release() with correct visibility timeout', function (): void {
    $encoded = base64_encode('{"job":"TestJob","data":{}}');

    $client = Mockery::mock(AzureQueueClient::class);
    $client->expects('updateMessage')
        ->with('default', 'msg-001', 'receipt-abc', $encoded, 30)
        ->once();

    $job = makeJob($client, encodedBody: $encoded);
    $job->release(30);

    expect($job->isReleased())->toBeTrue();
});

it('clamps negative delay to zero on release()', function (): void {
    $encoded = base64_encode('{}');

    $client = Mockery::mock(AzureQueueClient::class);
    $client->expects('updateMessage')
        ->withArgs(fn ($q, $id, $rcpt, $body, int $vt) => $vt === 0)
        ->once();

    makeJob($client, encodedBody: $encoded)->release(-10);
});

it('preserves the encoded body when releasing so compressed payloads stay compressed', function (): void {
    $payload = str_repeat('B', 50_000);
    $compressed = 'gz:'.base64_encode((string) gzcompress($payload));

    $message = new QueueMessage('msg-2', 'rcpt-2', $payload, 1);

    $client = Mockery::mock(AzureQueueClient::class);
    $client->expects('updateMessage')
        ->withArgs(fn ($q, $id, $rcpt, string $body, $vt) => $body === $compressed)
        ->once();

    $job = makeJob($client, $message, $compressed);
    $job->release(0);
});
