<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 05.09.18
 * Time: 19:01
 */

namespace TS\PhpService;


interface MemoryWatcherListenerInterface
{


    function onMemoryLeakDetected(int $grew, int $withinSeconds, string $message): void;

    function onMemoryLimitWarning(int $limit, int $actual, string $message): void;

    function onMemoryLimitReached(int $limit, int $actual, string $message): void;


}
