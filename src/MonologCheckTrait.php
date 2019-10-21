<?php


namespace TS\PhpService;


trait MonologCheckTrait
{

    protected function checkMonolog(bool $clearHandlers, bool $redirect, ServiceConsoleLogger $serviceConsoleLogger, MonologCheck $check): void
    {
        if (!$check->hasMonolog()) {
            return;
        }

        $done = [];

        if ($clearHandlers) {
            $check->clearMonologHandlersAndProcessors();
            $done[] = 'Cleared Monolog handlers and processors.';
            return;
        }

        if ($redirect) {
            $check->redirectMonolog($serviceConsoleLogger);
            $done[] = 'Redirecting Monolog to console.';
            return;
        }

        if (count($done) > 0) {
            $serviceConsoleLogger->info(join(' ', $done));
        }
    }

}