<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 13.09.18
 * Time: 19:06
 */

namespace TS\PhpService;


use Psr\Log\LoggerInterface;


trait CheckSqlLoggerTrait
{


    protected function checkSqlLogger(bool $remove, bool $ignore, LoggerInterface $logger, DoctrineSqlLoggerCheck $check): void
    {
        if (!$check->hasOffenses()) {
            return;
        }

        if ($ignore) {
            $offendingNames = $check->getOffendingManagerNames();
            $msg = 'Ignoring ' . count($offendingNames) . ' Entity Managers with an SQL logger. You will have a memory leak.';
            $logger->warning($msg, [
                'offending_managers' => $offendingNames
            ]);
            return;
        }

        if ($remove) {
            $offendingNames = $check->getOffendingManagerNames();
            $msg = 'Removed SQL loggers from ' . count($offendingNames) . ' Entity Managers to prevent memory leaks.';

            $check->removeSqlLoggers();

            $logger->info($msg, [
                'offending_managers' => $offendingNames
            ]);
            return;
        }

        $msg = $check->getOffenseMessage()
            . ' If you are sure that this is okay, run this command with the flag --ignore-sql-logger or use --remove-sql-logger.';
        throw new \InvalidArgumentException($msg);
    }


}
