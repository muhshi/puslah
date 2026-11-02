<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

// Register Nutgram PSR-4 autoloader
spl_autoload_register(function ($class) {
    $prefixes = [
        'SergiX44\\Nutgram\\' => dirname(__DIR__) . '/vendor/nutgram/nutgram/src/',
        'Nutgram\\Laravel\\' => dirname(__DIR__) . '/vendor/nutgram/laravel/src/',
        'SergiX44\\Hydrator\\' => dirname(__DIR__) . '/vendor/nutgram/hydrator/src/',
        'SergiX44\\Container\\' => dirname(__DIR__) . '/vendor/sergix44/container/src/',
    ];
    foreach ($prefixes as $prefix => $baseDir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) === 0) {
            $relativeClass = substr($class, $len);
            $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
            if (file_exists($file)) {
                require_once $file;
                return true;
            }
        }
    }
    return false;
});

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
