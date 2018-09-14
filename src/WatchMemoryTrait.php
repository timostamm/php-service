<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 13.09.18
 * Time: 19:06
 */

namespace TS\PhpService;


use Psr\Log\LoggerInterface;
use React\EventLoop\LoopInterface;


trait WatchMemoryTrait
{

    private function watchMemory(ShutdownableInterface $target, int $limitWarn, int $limitHard, int $limitLeak, LoopInterface $loop, LoggerInterface $logger): void
    {
        $listener = new class($target, $logger) implements MemoryWatcherListenerInterface
        {

            private $target;
            private $logger;

            public function __construct(ShutdownableInterface $target, LoggerInterface $logger)
            {
                $this->target = $target;
                $this->logger = $logger;
            }

            public function onMemoryLeakDetected(int $grew, int $withinSeconds, string $message): void
            {
                $this->logger->alert($message);
            }

            public function onMemoryLimitWarning(int $limit, int $actual, string $message): void
            {
                $this->logger->alert($message);
            }

            public function onMemoryLimitReached(int $limit, int $actual, string $message): void
            {
                $this->logger->emergency($message);

                $this->target->shutdown(SIGTERM);
            }

        };

        $watcher = new MemoryWatcher($listener, $limitWarn, $limitHard, $limitLeak);
        $watcher->attach($loop);
    }


}
