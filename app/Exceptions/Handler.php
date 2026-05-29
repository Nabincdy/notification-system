<?php


namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

final class Handler extends ExceptionHandler
{
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function register(): void
    {
        $this->renderable(function (ValidationException $e, Request $request): JsonResponse {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed.',
                    'errors'  => $e->errors(),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        });

        $this->renderable(function (HttpException $e, Request $request): JsonResponse {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage() ?: Response::$statusTexts[$e->getStatusCode()] ?? 'HTTP Error',
                    'errors'  => [],
                ], $e->getStatusCode());
            }
        });

        $this->renderable(function (Throwable $e, Request $request): ?JsonResponse {
            if ($request->expectsJson()) {
                $statusCode = method_exists($e, 'getStatusCode')
                    ? $e->getStatusCode()
                    : Response::HTTP_INTERNAL_SERVER_ERROR;

                return response()->json([
                    'success' => false,
                    'message' => app()->isProduction()
                        ? 'An unexpected error occurred.'
                        : $e->getMessage(),
                    'errors'  => [],
                ], $statusCode);
            }

            return null;
        });
    }
}
