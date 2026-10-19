<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Test-only mock response factory of the HTTP transport: any HTTP call that
 * reaches it was not mocked by its test, so it fails instead of going out.
 * Adapter tests inject their own MockHttpClient and never get here.
 */
final class UnmockedHttpCallGuard
{
    /**
     * @param array<mixed> $options
     */
    public function __invoke(string $method, string $url, array $options = []): ResponseInterface
    {
        throw new \LogicException(\sprintf('Unmocked HTTP call in tests: %s %s', $method, $url));
    }
}
