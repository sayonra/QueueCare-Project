<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->respond(function (Response $response, Throwable $exception, Request $request): Response {
            if (! $request->is('api/*') || $response->getStatusCode() < 400) {
                return $response;
            }

            $status = $response->getStatusCode();
            $codes = [
                400 => 'bad_request',
                401 => 'unauthenticated',
                403 => 'forbidden',
                404 => 'not_found',
                405 => 'method_not_allowed',
                422 => 'validation_failed',
                429 => 'rate_limited',
                503 => 'service_unavailable',
            ];
            $messages = [
                400 => 'The request could not be processed.',
                401 => 'Authentication is required.',
                403 => 'You do not have access to this resource.',
                404 => 'The requested resource was not found.',
                405 => 'This method is not allowed for the resource.',
                422 => 'The submitted data is invalid.',
                429 => 'Too many requests. Please try again later.',
                503 => 'The service is temporarily unavailable.',
            ];

            $details = $exception instanceof ValidationException ? $exception->errors() : (object) [];

            return response()->json([
                'error' => [
                    'code' => $codes[$status] ?? 'server_error',
                    'message' => $messages[$status] ?? 'An unexpected error occurred.',
                    'details' => $details,
                ],
            ], $status, $response->headers->all());
        });
    })->create();
