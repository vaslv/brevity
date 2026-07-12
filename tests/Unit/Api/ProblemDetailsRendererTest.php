<?php

namespace Tests\Unit\Api;

use App\Http\Api\ProblemDetailsRenderer;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Stage 2 of docs/07-plans.md — RFC 7807 renderer edge cases.
 *
 * 5xx responses must never leak internal exception messages, and 429 must
 * keep the throttle headers (Retry-After) alongside the problem+json body.
 */
class ProblemDetailsRendererTest extends TestCase
{
    public function test_a_500_http_exception_never_leaks_its_message(): void
    {
        $response = ProblemDetailsRenderer::render(
            new HttpException(500, 'db password is 123')
        );

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame('server-error', $response->getData(true)['type']);
        $this->assertStringNotContainsString('db password', $response->getContent());
    }

    public function test_a_throttle_exception_keeps_retry_after_header(): void
    {
        $response = ProblemDetailsRenderer::render(
            new ThrottleRequestsException('Too Many Attempts.', null, ['Retry-After' => '60'])
        );

        $this->assertSame(429, $response->getStatusCode());
        $this->assertSame('too-many-requests', $response->getData(true)['type']);
        $this->assertSame('60', $response->headers->get('Retry-After'));
        $this->assertSame('application/problem+json', $response->headers->get('Content-Type'));
    }

    public function test_an_unknown_throwable_maps_to_server_error(): void
    {
        $response = ProblemDetailsRenderer::render(new \RuntimeException('internal detail'));

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame('server-error', $response->getData(true)['type']);
        $this->assertStringNotContainsString('internal detail', $response->getContent());
    }
}
