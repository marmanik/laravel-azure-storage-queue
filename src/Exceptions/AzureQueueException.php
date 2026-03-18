<?php

declare(strict_types=1);

namespace Marmanik\AzureStorageQueue\Exceptions;

use MicrosoftAzure\Storage\Common\Exceptions\ServiceException;
use RuntimeException;

class AzureQueueException extends RuntimeException
{
    public static function fromServiceException(ServiceException $e): static
    {
        return new static(
            message: sprintf(
                'Azure Storage Queue error %d: %s',
                $e->getCode(),
                $e->getErrorText() ?? $e->getMessage(),
            ),
            code: (int) $e->getCode(),
            previous: $e,
        );
    }

    public static function payloadTooLarge(): static
    {
        return new static(
            'Message payload exceeds the 64 KB Azure Storage Queue limit even after compression.',
        );
    }
}
