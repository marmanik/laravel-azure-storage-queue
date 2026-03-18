<?php

declare(strict_types=1);

namespace Marmanik\AzureStorageQueue\Tests;

use Marmanik\AzureStorageQueue\AzureStorageQueueServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [AzureStorageQueueServiceProvider::class];
    }
}
