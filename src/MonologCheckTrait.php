<?php


namespace TS\PhpService;


/** @deprecated use MonologCheck::appply */
trait MonologCheckTrait
{

    /** @deprecated use MonologCheck::appply */
    protected function checkMonolog(bool $clearHandlers, bool $redirect, ServiceConsoleLogger $serviceConsoleLogger, MonologCheck $check): void
    {
        $check->apply($clearHandlers, $redirect ? $serviceConsoleLogger : null, $serviceConsoleLogger);
    }

}
