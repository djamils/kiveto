<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Infrastructure\Vat;

use App\Context\Client\Infrastructure\Vat\ViesErrorMapper;
use App\Shared\Application\ExternalApi\ExternalServiceUnavailable;
use App\Shared\Infrastructure\ExternalApi\ExternalApiResponse;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

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
                new ViesErrorMapper(new NullLogger())->map(new ExternalApiResponse(200, $data));
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
                    new ViesErrorMapper(new NullLogger())->map(new ExternalApiResponse(200, $data));
                    self::fail($code . ' must not read as an invalid number.');
                } catch (ExternalServiceUnavailable $e) {
                    self::assertSame(\sprintf('vies is unavailable: %s.', $code), $e->getMessage());
                }
            }
        }
    }

    public function testAnInvalidInputIsLoggedAsAnErrorWithoutTheNumber(): void
    {
        $logs   = new TestHandler();
        $mapper = new ViesErrorMapper(new Logger('external_api', [$logs]));

        try {
            $mapper->map(new ExternalApiResponse(400, ['vatNumber' => '00000000000', 'errorWrappers' => [['error' => 'INVALID_INPUT']]]));
            self::fail('An outage was expected.');
        } catch (ExternalServiceUnavailable) {
        }

        $records = $logs->getRecords();
        self::assertCount(1, $records);
        self::assertSame(Level::Error, $records[0]->level);
        self::assertSame(['service' => 'vies', 'code' => 'INVALID_INPUT', 'status' => 400], $records[0]->context);

        $mapper->map(new ExternalApiResponse(200, ['valid' => false]));
        try {
            $mapper->map(new ExternalApiResponse(200, ['errorWrappers' => [['error' => 'MS_UNAVAILABLE']]]));
        } catch (ExternalServiceUnavailable) {
        }
        self::assertCount(1, $logs->getRecords());
    }

    public function testTheValidFlagIsTheAnswer(): void
    {
        self::assertTrue(new ViesErrorMapper(new NullLogger())->map(new ExternalApiResponse(200, ['valid' => true, 'userError' => 'VALID']))->isValid);
        self::assertFalse(new ViesErrorMapper(new NullLogger())->map(new ExternalApiResponse(200, ['valid' => false, 'userError' => 'INVALID']))->isValid);
    }

    public function testAnUnknownErrorCodeIsDown(): void
    {
        $this->expectException(ExternalServiceUnavailable::class);
        $this->expectExceptionMessage('vies is unavailable: VAT_BLOCKED.');

        new ViesErrorMapper(new NullLogger())->map(new ExternalApiResponse(200, ['errorWrappers' => ['x', ['error' => 'VAT_BLOCKED']]]));
    }

    public function testAClientErrorWithoutCodeIsDown(): void
    {
        $this->expectException(ExternalServiceUnavailable::class);
        $this->expectExceptionMessage('vies is unavailable: HTTP 404.');

        new ViesErrorMapper(new NullLogger())->map(new ExternalApiResponse(404, null));
    }

    public function testAnUnreadableAnswerIsDown(): void
    {
        $this->expectException(ExternalServiceUnavailable::class);
        $this->expectExceptionMessage('vies is unavailable: unreadable answer.');

        new ViesErrorMapper(new NullLogger())->map(new ExternalApiResponse(200, ['valid' => 'yes']));
    }
}
