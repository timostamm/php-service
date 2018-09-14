<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 12.09.18
 * Time: 16:03
 */

namespace TS\PhpService;


use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use React\Promise\CancellablePromiseInterface;
use React\Promise\PromiseInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use function React\Promise\resolve;


abstract class AbstractService extends Command implements ShutdownableInterface
{


    use FileLockTrait;
    use CheckSqlLoggerTrait;
    use WatchMemoryTrait;


    /** @var LoopInterface */
    private $loop;

    /** @var DoctrineSqlLoggerCheck */
    private $doctrineLoggerCheck;

    /** @var LoggerInterface */
    private $logger;

    /** @var PromiseInterface */
    private $shutdownPromise;

    /**
     * The code to exit with is determined by shutdown()
     * @var int
     */
    private $exitCode = 0;


    private const STATE_STARTING = 'starting';
    private const STATE_RUNNING = 'running';
    private const STATE_SHUT_DOWN = 'shut_down';
    private const STATE_SHUT_DOWN_NOW = 'shut_down_now';


    private $currentState = self::STATE_STARTING;


    public function __construct(string $name = null, DoctrineSqlLoggerCheck $doctrineLoggerCheck)
    {
        parent::__construct($name);
        $this->doctrineLoggerCheck = $doctrineLoggerCheck;
    }


    protected function configure()
    {
        $this
            ->addOption('run-quiet', null, InputOption::VALUE_NONE, 'Only output messages during start and shutdown')
            ->addOption('ignore-sql-logger', null, InputOption::VALUE_NONE)
            ->addOption('remove-sql-logger', null, InputOption::VALUE_NONE)
            ->addOption('mem-limit-warn', null, InputOption::VALUE_REQUIRED, '', $this->getDefaultMemoryLimits()['warn'])
            ->addOption('mem-limit-hard', null, InputOption::VALUE_REQUIRED, '', $this->getDefaultMemoryLimits()['hard'])
            ->addOption('mem-leak-limit', null, InputOption::VALUE_REQUIRED, '', $this->getDefaultMemoryLimits()['leak']);
    }


    protected function createLogger(): LoggerInterface
    {
        return new NullLogger();
    }


    protected function initialize(InputInterface $input, OutputInterface $output)
    {

        $this->logger = new ServiceLogger($this->currentState, [self::STATE_RUNNING]);
        $this->logger->add($this->createConsoleLogger($input, $output), $input->getOption('run-quiet'));
        $this->logger->add($this->createLogger());

        $this->loop = $this->createLoop();

        $this->acquireLock();

        $this->addShutdownSignal(SIGINT);
        $this->addShutdownSignal(SIGTERM);

        $this->checkSqlLogger(
            $input->getOption('remove-sql-logger'),
            $input->getOption('ignore-sql-logger'),
            $this->logger,
            $this->doctrineLoggerCheck
        );

        $this->watchMemory(
            $this,
            (int)$input->getOption('mem-limit-warn'),
            (int)$input->getOption('mem-limit-hard'),
            (int)$input->getOption('mem-leak-limit'),
            $this->loop,
            $this->logger
        );

    }


    protected function createConsoleLogger(InputInterface $input, OutputInterface $output): LoggerInterface
    {
        return new ServiceConsoleLogger($output, $input->isInteractive());
    }


    final protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input instanceof ArgvInput) {
            $this->logger->info('Starting with arguments: ' . $input);
        }

        $this->currentState = self::STATE_STARTING;

        try {

            $this->onStart($input, $this->loop, $this->logger);

            $this->currentState = self::STATE_RUNNING;

            $this->logger->notice('Running.');

            $this->loop->run();

        } catch (\Throwable $error) {

            $this->logger->alert('Startup failed: ' . $error->getMessage(), [
                'error' => $error
            ]);

            $this->exitCode = 1;
        }

        $this->releaseLock();

        return $this->exitCode;
    }


    abstract protected function onStart(InputInterface $input, LoopInterface $loop, LoggerInterface $serviceLogger): void;


    /**
     *
     * This method is called on shutdown and has some time to
     * perform cleanups.
     *
     * Overwrite this method to perform your cleanup.
     *
     * The returned promise has to resolve with an exit code
     * (int). Exit code 0 is a clean shutdown, anything else
     * indicates a problem.
     *
     * If the promise rejects, the shutdown routine is assumed
     * to have failed.
     *
     *
     * @param int $signal the signal that triggered the shutdown
     * @return PromiseInterface <int | /Throwable>
     */
    protected function onShutdown(int $signal): PromiseInterface
    {
        return resolve(0);
    }


    public function shutdown(int $signal = 0): void
    {
        if ($this->currentState === self::STATE_SHUT_DOWN) {

            $this->currentState = self::STATE_SHUT_DOWN_NOW;

            $this->logger->warning('Shutdown interrupted.', [
                'signal' => $signal
            ]);

            if ($this->shutdownPromise instanceof CancellablePromiseInterface) {
                $this->shutdownPromise->cancel();
            }

            $this->exitLoop($signal);

            return;
        }


        if ($this->currentState !== self::STATE_RUNNING) {
            throw new \LogicException('shutdown() called while state = ' . $this->currentState);
        }


        $this->currentState = self::STATE_SHUT_DOWN;

        $this->logger->notice('Shutting down.', [
            'signal' => $signal
        ]);

        $this->shutdownPromise = $this->onShutdown($signal);

        $this->shutdownPromise->then(function ($exitCode) {

            return (int)$exitCode;

        }, function ($error) {

            if ($error instanceof \Throwable) {

                $this->logger->error('Shutdown routine failed: ' . $error->getMessage(), [
                    'error' => $error
                ]);

                return 1;

            } else {

                $this->logger->error('Shutdown routine failed.', [
                    'error' => $error
                ]);

                return is_int($error) ? $error : 1;
            }

        })->then(function (int $exitCode) {

            $this->exitLoop($exitCode);

        });

    }


    private function exitLoop(int $exitCode): void
    {
        if ($exitCode === 0) {
            $this->logger->notice('Clean shutdown completed.', ['exitCode' => $exitCode]);
        } else {
            $this->logger->warning('Shutdown completed.', ['exitCode' => $exitCode]);
        }
        $this->exitCode = $exitCode;
        $this->loop->stop();
    }


    protected function getDefaultMemoryLimits(): array
    {
        return [
            'warn' => MemoryWatcher::MB * 256,
            'hard' => MemoryWatcher::MB * 320,
            'leak' => 0
        ];
    }


    protected function createLoop(): LoopInterface
    {
        return Factory::create();
    }


    private function addShutdownSignal(int $signal): void
    {
        $this->loop->addSignal($signal, $func = function ($signal) use (&$func) {

            $this->logger->debug('Received signal: ' . $signal . '.');

            $this->shutdown($signal);

            if ($this->currentState === self::STATE_SHUT_DOWN_NOW) {
                $this->loop->removeSignal($signal, $func);
            }

        });
    }


    final public function setCode(callable $code)
    {
        throw new \LogicException('not supported');
    }


}