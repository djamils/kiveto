<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Infrastructure\Vat;

use App\Context\Client\Infrastructure\Vat\FixtureVatNumberVerifier;
use App\Shared\Application\ExternalApi\ExternalServiceUnavailable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FixtureVatNumberVerifier::class)]
final class FixtureVatNumberVerifierTest extends TestCase
{
    public function testTheDemoNumbersAreValid(): void
    {
        $verifier = new FixtureVatNumberVerifier();

        self::assertTrue($verifier->verify('FR31523456788')->isValid);
        self::assertTrue($verifier->verify('FR14441569738')->isValid);
    }

    public function testAnyOtherNumberIsInvalid(): void
    {
        self::assertFalse(new FixtureVatNumberVerifier()->verify('FR40812345676')->isValid);
    }

    public function testTheOutageSirensNumberIsDown(): void
    {
        try {
            new FixtureVatNumberVerifier()->verify('FR00999999998');
            self::fail('An outage was expected.');
        } catch (ExternalServiceUnavailable $e) {
            self::assertFalse($e->isThrottled());
            self::assertSame('vies', $e->service());
        }
    }
}
