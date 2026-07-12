<?php

declare(strict_types=1);

namespace Piko\Tests\Infrastructure;

use PHPUnit\Event\TestRunner\ExecutionFinished;
use PHPUnit\Event\TestRunner\ExecutionFinishedSubscriber;

final class FinishedSubscriber implements ExecutionFinishedSubscriber
{
    public function notify(ExecutionFinished $event): void
    {
        $sqliteDb = sys_get_temp_dir() . '/test_post.sqlite';

        if (file_exists($sqliteDb)) {
            unlink($sqliteDb);
        }
    }
}
