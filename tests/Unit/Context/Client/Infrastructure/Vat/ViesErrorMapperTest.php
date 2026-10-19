<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Infrastructure\Vat;

use App\Context\Client\Infrastructure\Vat\ViesErrorMapper;
use App\Shared\Application\ExternalApi\ExternalServiceUnavailable;
use App\Shared\Infrastructure\ExternalApi\ExternalApiResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ViesErrorMapper::class)]
final class ViesErrorMapperTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function provideEveryOutageCodeIsDownCases(): iterable
    {
        foreach (['MS_UNAVAILABLE', 'SERVICE_UNAVAILABLE', 'TIMEOUT', 'GLOBAL_MAX_CONCURRENT_REQ', 'GLOBAL_MAX_CONCURRENT_REQ_TIME', 'MS_MAX_CONCURRENT_REQ', 'MS_MAX_CONCURRENT_REQ_TIME'] as $code) {
            yield $code => [$code];
        }
    }

    #[DataProvider('provideEveryOutageCodeIsDownCases')]
    public function testEveryOutageCodeIsDown(string $code): void
    {
        foreach ([['userError' => $code], ['errorWrappers' => [['error' => $code]]]] as $data) {
            try {
                new ViesErrorMapper()->map(new ExternalApiResponse(200, $data));
                self::fail('An outage was expected for ' . $code);
            } catch (ExternalServiceUnavailable $e) {
                self::assertFalse($e->isThrottled());
                self::assertSame(\sprintf('vies is unavailable: %s.', $code), $e->getMessage());
            }
        }
    }

    public function testAnyErrorCodeIsAnOutageNeverAnInvalidNumber(): void
    {
        foreach (['INVALID_INPUT', 'IP_BLOCKED', 'VAT_BLOCKED', 'INVALID_REQUESTER_INFO'] as $code) {
            foreach ([['valid' => false, 'userError' => $code], ['valid' => false, 'errorWrappers' => [['error' => $code]]]] as $data) {
                try {
                    new ViesErrorMapper()->map(new ExternalApiResponse(200, $data));
                    self::fail($code . ' must not read as an invalid number.');
                } catch (ExternalServiceUnavailable $e) {
                    self::assertSame(\sprintf('vies is unavailable: %s.', $code), $e->getMessage());
                }
            }
        }
    }

    public function testTheValidFlagIsTheAnswer(): void
    {
        self::assertTrue(new ViesErrorMapper()->map(new ExternalApiResponse(200, ['valid' => true, 'userError' => 'VALID']))->isValid);
        self::assertFalse(new ViesErrorMapper()->map(new ExternalApiResponse(200, ['valid' => false, 'userError' => 'INVALID']))->isValid);
    }

    public function testAnUnknownErrorCodeIsDown(): void
    {
        $this->expectException(ExternalServiceUnavailable::class);
        $this->expectExceptionMessage('vies is unavailable: VAT_BLOCKED.');

        new ViesErrorMapper()->map(new ExternalApiResponse(200, ['errorWrappers' => ['x', ['error' => 'VAT_BLOCKED']]]));
    }

    public function testAClientErrorWithoutCodeIsDown(): void
    {
        $this->expectException(ExternalServiceUnavailable::class);
        $this->expectExceptionMessage('vies is unavailable: HTTP 404.');

        new ViesErrorMapper()->map(new ExternalApiResponse(404, null));
    }

    public function testAnUnreadableAnswerIsDown(): void
    {
        $this->expectException(ExternalServiceUnavailable::class);
        $this->expectExceptionMessage('vies is unavailable: unreadable answer.');

        new ViesErrorMapper()->map(new ExternalApiResponse(200, ['valid' => 'yes']));
    }
}
