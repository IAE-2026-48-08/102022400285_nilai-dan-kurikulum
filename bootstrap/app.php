<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'iae.auth' => \App\Http\Middleware\CheckIaeKey::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*') || $request->wantsJson()) {
                $status = 500;
                $message = $e->getMessage();
                $errors = null;

                if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
                    $status = $e->getStatusCode();
                    $message = $e->getMessage() ?: 'Http Exception';
                } elseif ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                    $status = 404;
                    $message = 'Resource not found';
                } elseif ($e instanceof \Illuminate\Validation\ValidationException) {
                    $status = 422;
                    $message = $e->getMessage();
                    $errors = $e->errors();
                }

                if (empty($message)) {
                    $message = 'An unexpected error occurred';
                }

                return response()->json([
                    'status' => 'error',
                    'message' => $message,
                    'errors' => $errors
                ], $status)->header('Content-Type', 'application/json; charset=utf-8');
            }
        });
    })->create();
