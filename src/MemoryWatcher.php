<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 05.09.18
 * Time: 19:01
 */

namespace TS\PhpService;


use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;


class MemoryWatcher
{

    /** @var int */
    const MB = 1024 * 1024;

    const DEFAULT_MEMORY_LIMIT_WARN = '256mb';
    const DEFAULT_MEMORY_LIMIT_HARD = '320mb';
    const DEFAULT_LEAK_DETECTION_LIMIT = '64mb';


    /** @var LoopInterface */
    private $loop;

    /** @var bool */
    private $attached = false;

    /** @var int */
    private $settleDelay = 120;

    /** @var int */
    private $settleTimestamp;

    /** @var int */
    private $settleMem;

    /** @var bool */
    private $settled = false;

    /** @var TimerInterface */
    private $checkTimer;

    /** @var int */
    private $checkInterval = 10;

    /** @var int */
    private $memoryLimitWarn;

    /** @var int */
    private $memoryLimitHard;

    /** @var int */
    private $leakDetectionLimit;

    /** @var bool */
    private $leakDetected = false;

    /** @var bool */
    private $limitHardReached = false;

    /** @var bool */
    private $limitWarnReached = false;

    private $listener;


    public function __construct(MemoryWatcherListenerInterface $listener)
    {
        $this->listener = $listener;
        $this->setLimits();
    }


    public function getCheckInterval(): int
    {
        return $this->checkInterval;
    }


    public function setCheckInterval(int $checkInterval): void
    {
        if ($this->attached) {
            throw new \LogicException('Already attached.');
        }
        $this->checkInterval = $checkInterval;
    }


    /**
     * @param int|string $memoryLimitWarn
     * @param int|string $memoryLimitHard
     * @param int|string $leakDetectionLimit
     */
    public function setLimits(
        $memoryLimitWarn = self::DEFAULT_MEMORY_LIMIT_WARN,
        $memoryLimitHard = self::DEFAULT_MEMORY_LIMIT_HARD,
        $leakDetectionLimit = self::DEFAULT_LEAK_DETECTION_LIMIT
    )
    {
        if ($this->attached) {
            throw new \LogicException('Already attached.');
        }
        $this->memoryLimitWarn = $this->parseBytes($memoryLimitWarn);
        $this->memoryLimitHard = $this->parseBytes($memoryLimitHard);
        $this->leakDetectionLimit = $this->parseBytes($leakDetectionLimit);
    }

    /**
     * @param int | string $val
     * @return int
     */
    private function parseBytes($val): int
    {
        if (is_int($val)) {
            return $val;
        }
        if (is_string($val)) {

            preg_match('/^([0-9]+)([a-zA-Z]+)$/', $val, $matches);
            if (count($matches) !== 3) {
                throw new \InvalidArgumentException('Invalid value ' . $val);
            }

            $amount = (int)$matches[1];
            $unit = strtolower($matches[2]);

            if ($unit === 'm' || $unit === 'mb') {
                $bytes = $amount * 1024 * 1024;
            } else if ($unit === 'k' || $unit === 'kb') {
                $bytes = $amount * 1024;
            } else if ($unit === 'b') {
                $bytes = $amount;
            } else {
                throw new \InvalidArgumentException('Unknown unit ' . $unit . ' for value ' . $val);
            }
            return $bytes;
        }
    }


    public function getMemoryLimitWarn(): int
    {
        return $this->memoryLimitWarn;
    }

    public function getMemoryLimitHard(): int
    {
        return $this->memoryLimitHard;
    }

    public function getLeakDetectionLimit(): int
    {
        return $this->leakDetectionLimit;
    }


    public function attach(LoopInterface $loop): void
    {
        if ($this->attached) {
            throw new \LogicException('Already attached.');
        }
        $this->attached = true;
        $this->loop = $loop;
        $loop->addTimer($this->settleDelay, function () {
            $this->settle();
        });
        $this->checkTimer = $this->loop->addPeriodicTimer($this->checkInterval, function () {
            $this->check();
        });
    }


    protected function settle(): void
    {
        gc_collect_cycles();
        $this->settleTimestamp = time();
        $this->settleMem = memory_get_usage();
        $this->settled = true;
    }


    protected function check(): void
    {
        gc_collect_cycles();
        $mem = memory_get_usage();

        if ($this->memoryLimitWarn > 0 && !$this->limitWarnReached && $mem > $this->memoryLimitWarn) {
            $this->limitWarnReached = true;
            $this->onMemLimitWarn($mem);
        }

        if ($this->memoryLimitHard > 0 && !$this->limitHardReached && $mem > $this->memoryLimitHard) {
            $this->limitHardReached = true;
            $this->onMemLimitHard($mem);
        }

        if ($this->settled) {
            $grow = $mem - $this->settleMem;

            if ($this->leakDetectionLimit > 0 && !$this->leakDetected && $grow > $this->leakDetectionLimit) {
                $this->leakDetected = true;
                $durSeconds = time() - $this->settleTimestamp;
                $this->onLeakDetected($this->settleMem, $mem, $grow, $durSeconds);
            }

            if ($this->leakDetected && $this->limitWarnReached && $this->limitHardReached) {
                $this->loop->cancelTimer($this->checkTimer);
            }
        }
    }


    protected function onMemLimitWarn(int $currentMem): void
    {
        $msg = sprintf(
            'Memory warning limit of %s bytes reached. Current memory usage is %s bytes and hard limit is %s bytes.',
            number_format($this->memoryLimitWarn, 0, '.', ','),
            number_format($currentMem, 0, '.', ','),
            number_format($this->memoryLimitHard, 0, '.', ',')
        );

        $this->listener->onMemoryLimitWarning($this->memoryLimitWarn, $currentMem, $msg);
    }


    protected function onMemLimitHard(int $currentMem): void
    {
        $msg = sprintf(
            'Hard memory limit of %s bytes reached. Current memory usage is %s bytes. ',
            number_format($this->memoryLimitHard, 0, '.', ','),
            number_format($currentMem, 0, '.', ',')
        );

        $this->listener->onMemoryLimitReached($this->memoryLimitHard, $currentMem, $msg);
    }


    protected function onLeakDetected(int $settleMem, int $currentMem, int $memGrewBy, int $duringSeconds): void
    {
        $msg = sprintf(
            'Memory leak detected. Memory usage grew from %s bytes to %s bytes within %s seconds.',
            number_format($settleMem, 0, '.', ','),
            number_format($currentMem, 0, '.', ','),
            $duringSeconds
        );

        $this->listener->onMemoryLeakDetected($memGrewBy, $currentMem, $msg);

    }


}
