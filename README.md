# marmanik/laravel-azure-storage-queue

A Laravel queue driver backed by [Azure Storage Queues](https://learn.microsoft.com/en-us/azure/storage/queues/), built on the `microsoft/azure-storage-queue` SDK.

- Laravel 11 & 12 · PHP 8.2+
- Zero SDK types leak past `AzureQueueClientAdapter` — tests mock the `AzureQueueClient` interface
- Automatic gzip compression for payloads that exceed 64 KiB after base64 encoding
- Azurite (local emulator) supported via a custom endpoint

---

## Installation

```bash
composer require marmanik/laravel-azure-storage-queue
```

The service provider is auto-discovered via Laravel's package discovery.

---

## Configuration

Add the connection to `config/queue.php`. No separate config file is needed.

```php
// config/queue.php
'connections' => [

    'azure' => [
        'driver'       => 'azure-storage-queue',
        'account_name' => env('AZURE_STORAGE_ACCOUNT_NAME'),
        'account_key'  => env('AZURE_STORAGE_ACCOUNT_KEY'),
        'endpoint'     => env('AZURE_STORAGE_QUEUE_ENDPOINT'), // optional — see Azurite section
        'queue'        => env('AZURE_QUEUE_NAME', 'default'),
        'timeout'      => env('AZURE_QUEUE_TIMEOUT', 60),      // visibility timeout in seconds
    ],

],
```

Set the default connection if desired:

```php
'default' => env('QUEUE_CONNECTION', 'azure'),
```

`.env` for production:

```dotenv
AZURE_STORAGE_ACCOUNT_NAME=mystorageaccount
AZURE_STORAGE_ACCOUNT_KEY=base64key==
AZURE_QUEUE_NAME=my-queue
AZURE_QUEUE_TIMEOUT=60
```

---

## Creating the queue

Before dispatching jobs the queue must exist in Azure Storage. Use the bundled Artisan command:

```bash
php artisan azure-storage-queue:create-queue
# or specify a non-default connection
php artisan azure-storage-queue:create-queue --connection=azure
```

The command is idempotent — running it against an existing queue is safe.

---

## Usage

### Dispatching jobs

Nothing changes from standard Laravel queue usage:

```php
dispatch(new App\Jobs\SendWelcomeEmail($user));

// Delay
dispatch(new App\Jobs\SendWelcomeEmail($user))->delay(now()->addMinutes(5));

// Specific queue
dispatch(new App\Jobs\SendWelcomeEmail($user))->onQueue('emails');
```

### Running a worker

```bash
php artisan queue:work azure
php artisan queue:work azure --queue=emails
```

### Laravel Horizon

This driver is compatible with Horizon. Add the `azure` connection to `config/horizon.php` under `environments`:

```php
'environments' => [
    'production' => [
        'supervisor-azure' => [
            'connection' => 'azure',
            'queue'      => ['default'],
            'balance'    => 'simple',
            'processes'  => 10,
            'tries'      => 3,
        ],
    ],
],
```

> **Note:** Azure Storage Queues do not support priority ordering between queues at the storage level. Horizon's balancing works correctly but FIFO is best-effort across multiple workers.

### Custom TTL per dispatch

Pass `ttl` (seconds) in the options array when using `pushRaw` directly:

```php
Queue::connection('azure')->pushRaw($payload, 'default', ['ttl' => 3600]);
```

---

## Azurite local development

[Azurite](https://github.com/Azure/Azurite) is the official Azure Storage emulator. The queue service runs on port **10001**.

### Start Azurite

```bash
# Docker
docker run -p 10000:10000 -p 10001:10001 -p 10002:10002 mcr.microsoft.com/azure-storage/azurite

# npm
npx azurite --silent
```

### .env for Azurite

```dotenv
AZURE_STORAGE_ACCOUNT_NAME=devstoreaccount1
AZURE_STORAGE_ACCOUNT_KEY=Eby8vdM02xNOcqFlqUwJPLlmEtlCDXJ1OUzFT50uSRZ6IFsuFq2UVErCz4I6tq/K1SZFPTOtr/KBHBeksoGMGw==
AZURE_STORAGE_QUEUE_ENDPOINT=http://127.0.0.1:10001/devstoreaccount1
AZURE_QUEUE_NAME=default
```

Create the queue:

```bash
php artisan azure-storage-queue:create-queue
```

---

## How message encoding works

| Payload size (after base64) | Storage format |
|-----------------------------|----------------|
| ≤ 64 KiB                    | `base64(payload)` |
| > 64 KiB                    | `gz:` + `base64(gzcompress(payload))` |

The `gz:` prefix is detected transparently on `pop()`. If the compressed payload still exceeds 64 KiB an `AzureQueueException` is thrown.

---

## Architecture

```
AzureStorageQueueServiceProvider   — registers the 'azure-storage-queue' driver
AzureStorageQueueConnector         — ConnectorInterface: builds the queue from config
AzureStorageQueue                  — extends Illuminate\Queue\Queue, implements Queue contract
AzureStorageQueueJob               — extends Illuminate\Queue\Jobs\Job, wraps QueueMessage DTO
AzureQueueClientAdapter            — implements AzureQueueClient, owns all SDK calls
  Contracts/AzureQueueClient       — interface; SDK never crosses this boundary
  Data/QueueMessage                — readonly DTO: messageId, popReceipt, body, dequeueCount
  Exceptions/AzureQueueException   — wraps ServiceException from the SDK
  Commands/CreateQueueCommand      — php artisan azure-storage-queue:create-queue
```

---

## Limitations

| Constraint | Detail |
|---|---|
| Message size | 64 KiB per message (Azure hard limit). Automatic gzip compression extends the effective limit. |
| Max TTL | 7 days (604 800 seconds). Azure rejects higher values. |
| `later()` delay | Also limited to 7 days (implemented as initial visibility timeout). |
| FIFO | Azure Storage Queues are best-effort FIFO. Strict ordering is not guaranteed under concurrent workers. |
| Batch operations | `bulk()` loops individual `push()` calls; no native batch API. |

---

## Testing

```bash
composer test          # Pest
composer stan          # PHPStan level 8
composer format        # Pint
```

---

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## License

MIT — see [LICENSE.md](LICENSE.md).
