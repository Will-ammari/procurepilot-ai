<?php

use App\Http\Middleware\AttachRequestId;
use App\Support\ApiErrorResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            AttachRequestId::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return match (true) {
                $exception instanceof ValidationException => ApiErrorResponse::validation($exception->errors()),

                $exception instanceof AuthenticationException => ApiErrorResponse::make(
                    code: 'unauthenticated',
                    message: 'Authentication is required to access this resource.',
                    status: Response::HTTP_UNAUTHORIZED,
                    exception: $exception,
                ),

                $exception instanceof AuthorizationException => ApiErrorResponse::make(
                    code: 'forbidden',
                    message: 'You are not authorized to perform this action.',
                    status: Response::HTTP_FORBIDDEN,
                    exception: $exception,
                ),

                $exception instanceof AccessDeniedHttpException => ApiErrorResponse::make(
                    code: 'forbidden',
                    message: 'You are not authorized to perform this action.',
                    status: Response::HTTP_FORBIDDEN,
                    exception: $exception,
                ),

                $exception instanceof ModelNotFoundException || $exception instanceof NotFoundHttpException => ApiErrorResponse::make(
                    code: 'not_found',
                    message: 'The requested resource was not found.',
                    status: Response::HTTP_NOT_FOUND,
                    exception: $exception,
                ),

                $exception instanceof MethodNotAllowedHttpException => ApiErrorResponse::make(
                    code: 'method_not_allowed',
                    message: 'The HTTP method is not allowed for this endpoint.',
                    status: Response::HTTP_METHOD_NOT_ALLOWED,
                    exception: $exception,
                ),

                $exception instanceof ThrottleRequestsException => ApiErrorResponse::make(
                    code: 'too_many_requests',
                    message: 'Too many requests. Please retry later.',
                    status: Response::HTTP_TOO_MANY_REQUESTS,
                    exception: $exception,
                ),

                $exception instanceof HttpExceptionInterface => ApiErrorResponse::make(
                    code: 'http_error',
                    message: $exception->getMessage() !== ''
                        ? $exception->getMessage()
                        : 'The request could not be completed.',
                    status: $exception->getStatusCode(),
                    exception: $exception,
                ),

                default => ApiErrorResponse::make(
                    code: 'server_error',
                    message: 'An unexpected server error occurred.',
                    status: Response::HTTP_INTERNAL_SERVER_ERROR,
                    exception: $exception,
                ),
            };
        });
    })->create();
