<?php


namespace TS\PhpService;


use Monolog\Handler\AbstractHandler;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Output\OutputInterface;

/**
 */
class MonologCheck
{

    /**
     * @var \Monolog\Logger
     */
    private $monologLogger;


    /**
     * MonologCheck constructor.
     * @param \Monolog\Logger \Symfony\Bridge\Monolog\Logger | $monologLogger
     */
    public function __construct($monologLogger)
    {
        if (class_exists('\Monolog\Logger') && $monologLogger instanceof \Monolog\Logger) {
            $this->monologLogger = $monologLogger;
        }
        if (class_exists('\Symfony\Bridge\Monolog\Logger') && $monologLogger instanceof \Symfony\Bridge\Monolog\Logger) {
            $this->monologLogger = $monologLogger;
        }
    }


    /**
     * Apply Monolog checks
     *
     * @param bool $clearHandlers whether to clear monolog handlers and processors
     * @param LoggerInterface|null $redirectTarget redirect everything logged through monolog to this logger
     * @param LoggerInterface|OutputInterface|null $output log info about what was done to this logger our output
     */
    public function apply(bool $clearHandlers, ?LoggerInterface $redirectTarget, $output = null): void
    {
        if (!$this->hasMonolog()) {
            return;
        }

        $done = [];

        if ($clearHandlers) {
            $this->clearMonologHandlersAndProcessors();
            $done[] = 'Cleared handlers and processors.';
        }

        if ($redirectTarget) {
            $this->redirectMonolog($redirectTarget);
            $done[] = 'Redirecting to console.';
        }

        if (count($done) > 0) {
            if ($output instanceof OutputInterface) {
                $output->writeln('Monolog: ' . join(' ', $done));
            } else if ($output instanceof LoggerInterface) {
                $output->warning('Monolog: ' . join(' ', $done));
            }
        }
    }


    public function hasMonolog(): bool
    {
        return !!$this->monologLogger;
    }


    public function clearMonologHandlersAndProcessors(): void
    {
        if (!$this->hasMonolog()) {
            return;
        }
        while (!empty($this->monologLogger->getProcessors())) {
            $this->monologLogger->popProcessor();
        }
        while (!empty($this->monologLogger->getHandlers())) {
            $this->monologLogger->popHandler();
        }
    }


    public function redirectMonolog(LoggerInterface $targetLogger): void
    {
        if (!$this->hasMonolog()) {
            return;
        }

        $handler = new class($targetLogger) extends AbstractHandler {
            /** @var LoggerInterface */
            private $target;

            /**
             *  constructor.
             * @param LoggerInterface $target
             */
            public function __construct(LoggerInterface $target)
            {
                $this->target = $target;
            }


            public function handle(array $record)
            {
                $message = $record['message'] ?? '';
                $context = $record['context'] ?? [];
                $level = strtolower($record['level_name'] ?? LogLevel::INFO);
                $this->target->log($level, $message, $context);
            }
        };

        $this->monologLogger->pushHandler($handler);
    }

}
