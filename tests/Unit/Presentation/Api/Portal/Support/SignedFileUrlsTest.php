<?php

declare(strict_types=1);

namespace App\Tests\Unit\Presentation\Api\Portal\Support;

use App\Presentation\Api\Portal\Problem\PortalProblemMap;
use App\Presentation\Api\Portal\Support\SignedFileUrls;
use App\Presentation\Api\Shared\Problem\ApiProblem;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;

final class SignedFileUrlsTest extends TestCase
{
    private MockClock $clock;

    private SignedFileUrls $urls;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-10-31 10:00:00');

        // The route name and the reference type show up in the generated URL.
        $generator = new class implements UrlGeneratorInterface {
            public function setContext(RequestContext $context): void
            {
            }

            public function getContext(): RequestContext
            {
                return new RequestContext();
            }

            /**
             * @param array<string, mixed> $parameters
             */
            public function generate(
                string $name,
                array $parameters = [],
                int $referenceType = self::ABSOLUTE_PATH,
            ): string {
                $segments = array_map(
                    static fn (mixed $value): string => \is_scalar($value) ? (string) $value : '',
                    $parameters,
                );
                $host = self::ABSOLUTE_URL === $referenceType ? 'https://kiveto.test' : '';

                return $host . '/' . $name . '/' . implode('/', $segments);
            }
        };

        $this->urls = new SignedFileUrls(new UriSigner('s3cr3t', clock: $this->clock), $generator);
    }

    public function testASignedUrlIsValidForFiveMinutes(): void
    {
        $url = $this->urls->sign('portal_file', ['clinicId' => 'c1', 'documentId' => 'd1', 'version' => 2]);

        self::assertStringStartsWith('https://kiveto.test/portal_file/c1/d1/2?', $url);
        $expiration = $this->clock->now()->modify('+5 minutes')->getTimestamp();
        self::assertStringContainsString('_expiration=' . $expiration, $url);

        $this->clock->modify('+4 minutes 59 seconds');
        $this->urls->verify(Request::create($url));
        $this->addToAssertionCount(1);
    }

    public function testATamperedUrlIsNotFound(): void
    {
        $url = $this->urls->sign('portal_file', ['clinicId' => 'c1', 'documentId' => 'd1', 'version' => 2]);

        $this->assertNotFound(Request::create(str_replace('/d1/', '/d2/', $url)));
    }

    public function testAnExpiredUrlIsNotFound(): void
    {
        $url = $this->urls->sign('portal_file', ['clinicId' => 'c1', 'documentId' => 'd1', 'version' => 2]);
        $this->clock->modify('+5 minutes 1 second');

        $this->assertNotFound(Request::create($url));
    }

    public function testAnUnsignedUrlIsNotFound(): void
    {
        $this->assertNotFound(Request::create('https://kiveto.test/portal_file/c1/d1/2'));
    }

    private function assertNotFound(Request $request): void
    {
        try {
            $this->urls->verify($request);
            self::fail('The signature should have been refused.');
        } catch (ApiProblem $problem) {
            self::assertSame([PortalProblemMap::NOT_FOUND, 404], [$problem->getType(), $problem->getStatus()]);
        }
    }
}
