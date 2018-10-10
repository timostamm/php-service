<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 10.10.18
 * Time: 10:37
 */

namespace TS\PhpService;

use PHPUnit\Framework\TestCase;


class MemoryWatcherCase extends TestCase
{

    public function testParseLimit()
    {

        /** @var MemoryWatcherListenerInterface $l */
        $l = $this->createMock(MemoryWatcherListenerInterface::class);
        $w = new MemoryWatcher($l);

        $w->setLimits(1024);
        $this->assertSame(1024, $w->getMemoryLimitWarn());


        $w->setLimits('1k');
        $this->assertSame(1024, $w->getMemoryLimitWarn());


        $w->setLimits('1kb');
        $this->assertSame(1024, $w->getMemoryLimitWarn());


        $w->setLimits('1m');
        $this->assertSame(1024 * 1024, $w->getMemoryLimitWarn());


        $w->setLimits('1mb');
        $this->assertSame(1024 * 1024, $w->getMemoryLimitWarn());


    }

}