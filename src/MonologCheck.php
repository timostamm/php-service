<?php


namespace TS\PhpService;


use Monolog\Handler\AbstractHandler;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 */
class MonologCheck
{

    /**
     * @var \Monolog\Logger
     */
    private $monologLogger;


    public function __construct($monologLogger)
    {
        if (class_exists('\Monolog\Logger') && $monologLogger instanceof \Monolog\Logger) {
            $this->monologLogger = $monologLogger;
        }
        if (class_exists('\Symfony\Bridge\Monolog\Logger') && $monologLogger instanceof \Symfony\Bridge\Monolog\Logger) {
            $this->monologLogger = $monologLogger;
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

        $handler = new class($targetLogger) extends AbstractHandler
        {
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