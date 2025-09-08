<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function ($middleware) {
        // 1) Trusted Proxies
        $proxies = env('TRUSTED_PROXIES', '*');
        $headers = Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_HOST
            | Request::HEADER_X_FORWARDED_PORT
            | Request::HEADER_X_FORWARDED_PROTO
            | Request::HEADER_X_FORWARDED_AWS_ELB;

        $middleware->trustProxies($proxies, $headers);

        // 2) (Opsional tapi disarankan) Trusted Hosts
        $hosts = array_filter(array_map('trim', explode(',', (string) env('TRUSTED_HOSTS', 'puslah.bpsdemak.com'))));
        if (!empty($hosts)) {
            $middleware->trustHosts($hosts);
        }

        // middleware lainmu yang sudah ada…
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
