<?php

declare(strict_types=1);

use Marmanik\AzureStorageQueue\AzureStorageQueue;
use Marmanik\AzureStorageQueue\AzureStorageQueueJob;
use Marmanik\AzureStorageQueue\Contracts\AzureQueueClient;
use Marmanik\AzureStorageQueue\Data\QueueMessage;
use Marmanik\AzureStorageQueue\Exceptions\AzureQueueException;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function makeQueue(AzureQueueClient $client, string $default = 'default', int $timeout = 60): AzureStorageQueue
{
    $queue = new AzureStorageQueue($client, $default, $timeout);
    $queue->setContainer(app());
    $queue->setConnectionName('azure');

    return $queue;
}

// ---------------------------------------------------------------------------
// size()
// ---------------------------------------------------------------------------

it('returns the approximate message count', function (): void {
    $client = Mockery::mock(AzureQueueClient::class);
    $client->expects('getApproximateMessageCount')->with('default')->andReturn(7);

    $queue = makeQueue($client);

    expect($queue->size())->toBe(7);
});

it('uses a custom queue name for size()', function (): void {
    $client = Mockery::mock(AzureQueueClient::class);
    $client->expects('getApproximateMessageCount')->with('other')->andReturn(3);

    $queue = makeQueue($client);

    expect($queue->size('other'))->toBe(3);
});

// ---------------------------------------------------------------------------
// pushRaw()
// ---------------------------------------------------------------------------

it('encodes and sends a raw payload', function (): void {
    $payload = '{"job":"App\\\\Jobs\\\\TestJob","data":{}}';

    $client = Mockery::mock(AzureQueueClient::class);
    $client->expects('createMessage')
        ->withArgs(function (string $queue, string $body, $vt, $ttl) use ($payload): bool {
            return $queue === 'default'
                && base64_decode($body) === $payload
                && $vt === null
                && $ttl === null;
        })
        ->once();

    makeQueue($client)->pushRaw($payload, 'default');
});

it('forwards ttl option to createMessage', function (): void {
    $client = Mockery::mock(AzureQueueClient::class);
    $client->expects('createMessage')
        ->withArgs(fn ($q, $b, $vt, $ttl) => $ttl === 3600)
        ->once();

    makeQueue($client)->pushRaw('{"job":"test"}', 'default', ['ttl' => 3600]);
});

// ---------------------------------------------------------------------------
// later()
// ---------------------------------------------------------------------------

it('uses visibility timeout as initial delay for later()', function (): void {
    $client = Mockery::mock(AzureQueueClient::class);
    $client->expects('createMessage')
        ->withArgs(fn ($q, $b, int $vt, $ttl = null) => $vt === 120)
        ->once();

    makeQueue($client)->later(120, 'TestJob', '', 'default');
});

// ---------------------------------------------------------------------------
// pop()
// ---------------------------------------------------------------------------

it('returns null when the queue is empty', function (): void {
    $client = Mockery::mock(AzureQueueClient::class);
    $client->expects('listMessages')->with('default', 1, 60)->andReturn([]);

    expect(makeQueue($client)->pop())->toBeNull();
});

it('wraps a received message in AzureStorageQueueJob', function (): void {
    $payload = '{"job":"TestJob","data":{}}';
    $encoded = base64_encode($payload);

    $sdkMessage = new QueueMessage(
        messageId: 'msg-001',
        popReceipt: 'receipt-abc',
        body: $encoded,      // adapter puts the raw Azure text here
        dequeueCount: 1,
    );

    $client = Mockery::mock(AzureQueueClient::class);
    $client->expects('listMessages')->with('default', 1, 60)->andReturn([$sdkMessage]);

    $job = makeQueue($client)->pop();

    expect($job)->toBeInstanceOf(AzureStorageQueueJob::class)
        ->and($job->getJobId())->toBe('msg-001')
        ->and($job->getRawBody())->toBe($payload)
        ->and($job->attempts())->toBe(1);
});

// ---------------------------------------------------------------------------
// Payload encoding / decoding
// ---------------------------------------------------------------------------

it('round-trips a small payload through encode/decode', function (): void {
    $client = Mockery::mock(AzureQueueClient::class);
    $queue = makeQueue($client);

    $payload = '{"job":"TestJob","uuid":"abc"}';
    $encoded = $queue->encodePayload($payload);

    expect($encoded)->not->toStartWith('gz:')
        ->and($queue->decodePayload($encoded))->toBe($payload);
});

it('compresses a large payload and round-trips it', function (): void {
    $client = Mockery::mock(AzureQueueClient::class);
    $queue = makeQueue($client);

    // Build a payload that base64-encodes to > 64 KiB.
    $payload = str_repeat('A', 50_000);
    $encoded = $queue->encodePayload($payload);

    expect($encoded)->toStartWith('gz:')
        ->and($queue->decodePayload($encoded))->toBe($payload);
});

it('throws when the payload is too large even after compression', function (): void {
    $client = Mockery::mock(AzureQueueClient::class);
    $queue = makeQueue($client);

    // Random-ish bytes compress poorly; 200 KiB of random data stays large.
    $payload = base64_encode(random_bytes(200_000));

    expect(fn () => $queue->encodePayload($payload))
        ->toThrow(AzureQueueException::class);
});
