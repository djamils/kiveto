<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Infrastructure\ExternalApi;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Tests and CI never reach the network: an HTTP call without its own mock fails.
 */
final class NetworkGuardTest extends KernelTestCase
{
    public function testAnUnmockedCallThroughTheDefaultClientFails(): void
    {
        $client = self::getContainer()->get(HttpClientInterface::class);
        \assert($client instanceof HttpClientInterface);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Unmocked HTTP call in tests: GET https://recherche-entreprises.api.gouv.fr/search?q=calanques');

        $client->request('GET', 'https://recherche-entreprises.api.gouv.fr/search?q=calanques');
    }
}
