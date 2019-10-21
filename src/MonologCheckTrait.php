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
            $done[] = 'Cleared handlers and processors.';
        }

        if ($redirect) {
            $check->redirectMonolog($serviceConsoleLogger);
            $done[] = 'Redirecting to console.';
        }

        if (count($done) > 0) {
            $serviceConsoleLogger->warning('Monolog: ' . join(' ', $done));
        }
    }

}
