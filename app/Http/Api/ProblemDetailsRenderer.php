<?php

namespace App\Http\Api;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Exceptions\MissingAbilityException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * RFC 7807 Problem Details for /api/v1 (docs/03-api.md §11). Clients react to
 * the stable machine code in `type`, never to human-readable texts. Legacy
 * unversioned routes keep Laravel's default JSON error shape.
 */
class ProblemDetailsRenderer
{
    public static function render(Throwable $e): JsonResponse
    {
        [$status, $type, $title, $extra, $headers] = match (true) {
            $e instanceof ValidationException => [
                422, 'validation-error', 'The request failed validation.',
                ['errors' => $e->errors(), 'detail' => collect($e->errors())->flatten()->first()],
                [],
            ],
            $e instanceof AuthenticationException => [
                401, 'unauthenticated', 'A valid API token is required.', [], [],
            ],
            $e instanceof MissingAbilityException => [
                403, 'missing-ability', 'The token lacks the required ability.', [], [],
            ],
            $e instanceof AuthorizationException => [
                403, 'forbidden', 'This action is not authorized.', [], [],
            ],
            $e instanceof ModelNotFoundException => [
                404, 'not-found', 'The requested resource does not exist.', [], [],
            ],
            $e instanceof ThrottleRequestsException => [
                429, 'too-many-requests', 'Rate limit exceeded.', [], $e->getHeaders(),
            ],
            // The framework wraps authorization failures into an
            // AccessDeniedHttpException before render callbacks run — the
            // original exception survives as getPrevious().
            $e instanceof HttpExceptionInterface && $e->getStatusCode() === 403 => [
                403,
                $e->getPrevious() instanceof MissingAbilityException ? 'missing-ability' : 'forbidden',
                $e->getPrevious() instanceof MissingAbilityException
                    ? 'The token lacks the required ability.'
                    : 'This action is not authorized.',
                [],
                $e->getHeaders(),
            ],
            // 5xx never leaks the exception message (abort(500, ...) texts are
            // internal); 4xx messages come from our own aborts and are safe.
            $e instanceof HttpExceptionInterface && $e->getStatusCode() >= 500 => [
                $e->getStatusCode(), 'server-error', 'Internal server error.', [], $e->getHeaders(),
            ],
            $e instanceof HttpExceptionInterface => [
                $e->getStatusCode(),
                match ($e->getStatusCode()) {
                    404 => 'not-found',
                    default => 'http-error',
                },
                $e->getMessage() !== '' ? $e->getMessage() : 'HTTP error.',
                [],
                $e->getHeaders(),
            ],
            default => [
                500, 'server-error', 'Internal server error.', [], [],
            ],
        };

        return new JsonResponse(
            array_merge(['type' => $type, 'title' => $title, 'status' => $status], $extra),
            $status,
            array_merge($headers, ['Content-Type' => 'application/problem+json']),
        );
    }
}
