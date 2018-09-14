<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 14.09.18
 * Time: 00:04
 */

namespace TS\PhpService;


use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;


class ServiceLogger extends AbstractLogger
{

    /** @var string */
    private $state;

    /** @var string[] */
    private $quietStates;

    /** @var \SplObjectStorage */
    private $loggers;


    public function __construct(string & $currentState, array $quietStates)
    {
        $this->loggers = new \SplObjectStorage();
        $this->state = &$currentState;
        $this->quietStates = $quietStates;
    }


    public function log($level, $message, array $context = array())
    {
        foreach ($this->loggers as $logger) {

            /** @var bool $runQuiet */
            $runQuiet = $this->loggers[$logger];

            if ($runQuiet && in_array($this->state, $this->quietStates)) {
                continue;
            }

            $logger->log($level, $message, $context);
        }
    }


    public function add(LoggerInterface $logger, bool $runQuiet = false): void
    {
        $this->loggers->attach($logger, $runQuiet);
    }


}
