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


class DelegatingLogger extends AbstractLogger
{

    /** @var LoggerInterface[] */
    private $loggers;


    public function __construct()
    {
        $this->loggers = [];
    }


    public function log($level, $message, array $context = array())
    {
        foreach ($this->loggers as $logger) {
            $logger->log($level, $message, $context);
        }
    }


    public function add(LoggerInterface $logger): void
    {
        $this->loggers[] = $logger;
    }


}
