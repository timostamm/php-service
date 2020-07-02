<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 13.09.18
 * Time: 19:06
 */

namespace TS\PhpService;


use Psr\Log\LoggerInterface;


/** @deprecated use DoctrineSqlLoggerCheck::apply */
trait CheckSqlLoggerTrait
{


    /** @deprecated use DoctrineSqlLoggerCheck::apply */
    protected function checkSqlLogger(bool $remove, bool $ignore, LoggerInterface $logger, DoctrineSqlLoggerCheck $check): void
    {
        $check->apply($remove, $ignore, $logger);
    }


}
