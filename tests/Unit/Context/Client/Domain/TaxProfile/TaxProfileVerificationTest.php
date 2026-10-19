<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Domain\TaxProfile;

use App\Context\Client\Domain\TaxProfile\CompanyCheck;
use App\Context\Client\Domain\TaxProfile\CompanyCheckOutcome;
use App\Context\Client\Domain\TaxProfile\TaxProfileVerification;
use App\Context\Client\Domain\TaxProfile\TaxProfileWarning;
use App\Context\Client\Domain\TaxProfile\VatCheck;
use App\Context\Client\Domain\TaxProfile\VatCheckOutcome;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(TaxProfileVerification::class)]
#[CoversClass(CompanyCheck::class)]
#[CoversClass(VatCheck::class)]
final class TaxProfileVerificationTest extends TestCase
{
    private const string NOW = '2026-10-19 10:00:00';

    public function testAnUncheckedVerificationHasNothing(): void
    {
        $verification = TaxProfileVerification::unchecked();

        self::assertSame(CompanyCheckOutcome::UNCHECKED, $verification->company()->outcome());
        self::assertNull($verification->company()->registryName());
        self::assertNull($verification->company()->checkedAt());
        self::assertSame(VatCheckOutcome::UNCHECKED, $verification->vat()->outcome());
        self::assertNull($verification->vat()->checkedAt());
        self::assertNull($verification->requestedAt());
    }

    public function testACheckKeepsItsOutcomeNameAndDate(): void
    {
        $at    = new \DateTimeImmutable('2026-10-18 08:12:00');
        $check = CompanyCheck::checked(CompanyCheckOutcome::ACTIVE, '  EARL ELEVAGE DES CALANQUES ', $at);

        self::assertSame(CompanyCheckOutcome::ACTIVE, $check->outcome());
        self::assertSame('EARL ELEVAGE DES CALANQUES', $check->registryName());
        self::assertSame($at, $check->checkedAt());
        self::assertNull(CompanyCheck::checked(CompanyCheckOutcome::NOT_FOUND, '  ', $at)->registryName());
    }

    public function testAnUncheckedOutcomeCarriesNoDate(): void
    {
        $at = new \DateTimeImmutable(self::NOW);

        self::assertTrue(CompanyCheck::checked(CompanyCheckOutcome::UNCHECKED, 'X', $at)->equals(CompanyCheck::unchecked()));
        self::assertTrue(VatCheck::checked(VatCheckOutcome::UNCHECKED, $at)->equals(VatCheck::unchecked()));
        self::assertSame($at, VatCheck::checked(VatCheckOutcome::VALID, $at)->checkedAt());
    }

    public function testEquality(): void
    {
        $at = new \DateTimeImmutable('2026-10-18 08:12:00');
        $a  = self::verification(CompanyCheckOutcome::ACTIVE, VatCheckOutcome::VALID, 'X', $at, $at);

        self::assertTrue($a->equals(self::verification(CompanyCheckOutcome::ACTIVE, VatCheckOutcome::VALID, 'X', $at, $at)));
        self::assertFalse($a->equals(self::verification(CompanyCheckOutcome::ACTIVE, VatCheckOutcome::VALID, 'Y', $at, $at)));
        self::assertFalse($a->equals(self::verification(CompanyCheckOutcome::ACTIVE, VatCheckOutcome::INVALID, 'X', $at, $at)));
        self::assertFalse($a->equals($a->withRequestedAt(null)));
        self::assertFalse($a->equals(self::verification(CompanyCheckOutcome::ACTIVE, VatCheckOutcome::VALID, 'X', $at->modify('+1 second'), $at)));
    }

    public function testWithRequestedAtKeepsTheChecks(): void
    {
        $at           = new \DateTimeImmutable(self::NOW);
        $verification = self::verification(CompanyCheckOutcome::CEASED, VatCheckOutcome::INVALID, 'X', $at);
        $requested    = $verification->withRequestedAt($at);

        self::assertSame($at, $requested->requestedAt());
        self::assertTrue($requested->company()->equals($verification->company()));
        self::assertTrue($requested->vat()->equals($verification->vat()));
    }

    /**
     * @return iterable<string, array{string|null, bool}>
     */
    public static function providePendingMeansRequestedLessThanADayAgoCases(): iterable
    {
        yield 'never requested' => [null, false];
        yield 'just now' => ['2026-10-19 10:00:00', true];
        yield 'one second short of a day' => ['2026-10-18 10:00:01', true];
        yield 'exactly a day' => ['2026-10-18 10:00:00', false];
        yield 'two days' => ['2026-10-17 09:00:00', false];
    }

    #[DataProvider('providePendingMeansRequestedLessThanADayAgoCases')]
    public function testPendingMeansRequestedLessThanADayAgo(?string $requestedAt, bool $pending): void
    {
        $verification = TaxProfileVerification::unchecked(null === $requestedAt ? null : new \DateTimeImmutable($requestedAt));

        self::assertSame($pending, $verification->isPending(new \DateTimeImmutable(self::NOW)));
    }

    /**
     * @return iterable<string, array{CompanyCheckOutcome, VatCheckOutcome, string, bool}>
     */
    public static function provideStalenessCases(): iterable
    {
        yield 'never checked' => [CompanyCheckOutcome::UNCHECKED, VatCheckOutcome::UNCHECKED, '2026-10-18', true];
        yield 'registry unavailable' => [CompanyCheckOutcome::UNAVAILABLE, VatCheckOutcome::VALID, '2026-10-18', true];
        yield 'vies unavailable' => [CompanyCheckOutcome::ACTIVE, VatCheckOutcome::UNAVAILABLE, '2026-10-18', true];
        yield 'checked yesterday' => [CompanyCheckOutcome::ACTIVE, VatCheckOutcome::VALID, '2026-10-18', false];
        yield 'ceased yesterday' => [CompanyCheckOutcome::CEASED, VatCheckOutcome::NOT_APPLICABLE, '2026-10-18', false];
        yield 'confidential yesterday' => [CompanyCheckOutcome::CONFIDENTIAL, VatCheckOutcome::INVALID, '2026-10-18', false];
        yield 'not found 29 days ago' => [CompanyCheckOutcome::NOT_FOUND, VatCheckOutcome::VALID, '2026-09-20 10:00:01', false];
        yield 'confidential 31 days ago' => [CompanyCheckOutcome::CONFIDENTIAL, VatCheckOutcome::VALID, '2026-09-18', true];
        yield 'active 31 days ago' => [CompanyCheckOutcome::ACTIVE, VatCheckOutcome::VALID, '2026-09-18', true];
    }

    #[DataProvider('provideStalenessCases')]
    public function testStaleness(CompanyCheckOutcome $company, VatCheckOutcome $vat, string $checkedAt, bool $stale): void
    {
        $verification = self::verification($company, $vat, 'X', new \DateTimeImmutable($checkedAt));

        self::assertSame($stale, $verification->isStale(new \DateTimeImmutable(self::NOW), new \DateInterval('P30D')));
    }

    /**
     * @return iterable<string, array{CompanyCheckOutcome, VatCheckOutcome, string|null, string|null, list<TaxProfileWarning>}>
     */
    public static function provideWarningsMatrixCases(): iterable
    {
        $same = 'Élevage des Calanques';
        $diff = 'Ferme des Calanques';
        $off  = 'EARL ELEVAGE DES CALANQUES';

        yield 'unchecked' => [CompanyCheckOutcome::UNCHECKED, VatCheckOutcome::UNCHECKED, null, $same, [TaxProfileWarning::UNVERIFIED]];
        yield 'active, valid, same name' => [CompanyCheckOutcome::ACTIVE, VatCheckOutcome::VALID, $off, $same, []];
        yield 'active, valid, other name' => [CompanyCheckOutcome::ACTIVE, VatCheckOutcome::VALID, $off, $diff, [TaxProfileWarning::NAME_MISMATCH]];
        yield 'active, no VAT, same name' => [CompanyCheckOutcome::ACTIVE, VatCheckOutcome::NOT_APPLICABLE, $off, $same, []];
        yield 'active, invalid, other name' => [CompanyCheckOutcome::ACTIVE, VatCheckOutcome::INVALID, $off, $diff, [TaxProfileWarning::VAT_INVALID, TaxProfileWarning::NAME_MISMATCH]];
        yield 'active, vies down, same name' => [CompanyCheckOutcome::ACTIVE, VatCheckOutcome::UNAVAILABLE, $off, $same, [TaxProfileWarning::UNVERIFIED]];
        yield 'active, vies down, other name' => [CompanyCheckOutcome::ACTIVE, VatCheckOutcome::UNAVAILABLE, $off, $diff, [TaxProfileWarning::NAME_MISMATCH, TaxProfileWarning::UNVERIFIED]];
        yield 'active, no registry name' => [CompanyCheckOutcome::ACTIVE, VatCheckOutcome::VALID, null, $diff, []];
        yield 'active, no entered name' => [CompanyCheckOutcome::ACTIVE, VatCheckOutcome::VALID, $off, null, []];
        yield 'ceased, valid, other name' => [CompanyCheckOutcome::CEASED, VatCheckOutcome::VALID, $off, $diff, [TaxProfileWarning::COMPANY_CEASED, TaxProfileWarning::NAME_MISMATCH]];
        yield 'ceased, invalid, same name' => [CompanyCheckOutcome::CEASED, VatCheckOutcome::INVALID, $off, $same, [TaxProfileWarning::COMPANY_CEASED, TaxProfileWarning::VAT_INVALID]];
        yield 'not found, invalid' => [CompanyCheckOutcome::NOT_FOUND, VatCheckOutcome::INVALID, null, $same, [TaxProfileWarning::COMPANY_NOT_FOUND, TaxProfileWarning::VAT_INVALID]];
        yield 'not found, vies down' => [CompanyCheckOutcome::NOT_FOUND, VatCheckOutcome::UNAVAILABLE, null, $same, [TaxProfileWarning::COMPANY_NOT_FOUND, TaxProfileWarning::UNVERIFIED]];
        yield 'confidential, valid' => [CompanyCheckOutcome::CONFIDENTIAL, VatCheckOutcome::VALID, null, $diff, [TaxProfileWarning::COMPANY_CONFIDENTIAL]];
        yield 'confidential with a name is not compared' => [CompanyCheckOutcome::CONFIDENTIAL, VatCheckOutcome::VALID, $off, $diff, [TaxProfileWarning::COMPANY_CONFIDENTIAL]];
        yield 'registry down, valid' => [CompanyCheckOutcome::UNAVAILABLE, VatCheckOutcome::VALID, null, $same, [TaxProfileWarning::UNVERIFIED]];
        yield 'registry down, invalid' => [CompanyCheckOutcome::UNAVAILABLE, VatCheckOutcome::INVALID, null, $same, [TaxProfileWarning::VAT_INVALID, TaxProfileWarning::UNVERIFIED]];
        yield 'both down' => [CompanyCheckOutcome::UNAVAILABLE, VatCheckOutcome::UNAVAILABLE, null, $same, [TaxProfileWarning::UNVERIFIED]];
    }

    /**
     * @param list<TaxProfileWarning> $expected
     */
    #[DataProvider('provideWarningsMatrixCases')]
    public function testWarningsMatrix(CompanyCheckOutcome $company, VatCheckOutcome $vat, ?string $registryName, ?string $entered, array $expected): void
    {
        $verification = self::verification($company, $vat, $registryName, new \DateTimeImmutable('2026-10-18'));

        self::assertSame($expected, $verification->warnings($entered));
    }

    private static function verification(
        CompanyCheckOutcome $company,
        VatCheckOutcome $vat,
        ?string $registryName,
        \DateTimeImmutable $checkedAt,
        ?\DateTimeImmutable $requestedAt = null,
    ): TaxProfileVerification {
        return TaxProfileVerification::of(
            CompanyCheck::checked($company, $registryName, $checkedAt),
            VatCheck::checked($vat, $checkedAt),
            $requestedAt,
        );
    }
}
