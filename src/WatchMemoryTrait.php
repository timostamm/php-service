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

    /** @var MemoryWatcher */
    private $memoryWatcher;


    /**
     * @param ShutdownableInterface $target
     * @param int|string $limitWarn
     * @param int|string $limitHard
     * @param int|string $limitLeak
     * @param LoopInterface $loop
     * @param LoggerInterface $logger
     */
    private function watchMemory(ShutdownableInterface $target, $limitWarn, $limitHard, $limitLeak, LoopInterface $loop, LoggerInterface $logger): void
    {
        if ($this->memoryWatcher) {
            throw new \LogicException('Memory watcher already set.');
        }
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

        $this->memoryWatcher = new MemoryWatcher($listener);
        $this->memoryWatcher->setLimits($limitWarn, $limitHard, $limitLeak);
        $this->memoryWatcher->attach($loop);
    }


}
