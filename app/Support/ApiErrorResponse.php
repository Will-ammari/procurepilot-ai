<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ApiErrorResponse
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public static function make(
        string $code,
        string $message,
        int $status = Response::HTTP_INTERNAL_SERVER_ERROR,
        array $meta = [],
        ?Throwable $exception = null,
    ): JsonResponse {
        $payload = [
            'error' => array_filter([
                'code' => $code,
                'message' => $message,
                'request_id' => request()->attributes->get('request_id'),
                'meta' => $meta !== [] ? $meta : null,
            ], static fn (mixed $value): bool => $value !== null),
        ];

        if (config('app.debug') && $exception instanceof Throwable) {
            $payload['debug'] = [
                'exception' => $exception::class,
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => collect($exception->getTrace())->take(5)->all(),
            ];
        }

        return response()->json($payload, $status);
    }

    /**
     * @param  array<string, array<int, string>>  $errors
     */
    public static function validation(array $errors): JsonResponse
    {
        $response = self::make(
            code: 'validation_failed',
            message: 'The given data was invalid.',
            status: Response::HTTP_UNPROCESSABLE_ENTITY,
            meta: ['fields' => $errors],
        );

        $data = $response->getData(true);
        $data['errors'] = $errors;

        return response()->json($data, Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
