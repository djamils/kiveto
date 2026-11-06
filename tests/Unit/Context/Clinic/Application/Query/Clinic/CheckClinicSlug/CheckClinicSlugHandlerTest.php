<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Application\Query\Clinic\CheckClinicSlug;

use App\Context\Clinic\Application\Query\Clinic\CheckClinicSlug\CheckClinicSlug;
use App\Context\Clinic\Application\Query\Clinic\CheckClinicSlug\CheckClinicSlugHandler;
use App\Context\Clinic\Application\Query\Clinic\CheckClinicSlug\ClinicSlugCheck;
use App\Context\Clinic\Domain\Repository\ClinicRepositoryInterface;
use App\Context\Clinic\Domain\ValueObject\ClinicSlug;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CheckClinicSlugHandlerTest extends TestCase
{
    #[DataProvider('provideAWellFormedSlugIsTakenOrAvailableCases')]
    public function testAWellFormedSlugIsTakenOrAvailable(bool $exists, ClinicSlugCheck $check): void
    {
        $clinics = $this->createMock(ClinicRepositoryInterface::class);
        $clinics->expects(self::once())->method('existsBySlug')
            ->with(self::callback(static fn (ClinicSlug $slug): bool => 'clinique-des-lilas' === $slug->toString()))
            ->willReturn($exists)
        ;

        self::assertSame($check, new CheckClinicSlugHandler($clinics)(new CheckClinicSlug(' clinique-des-lilas ')));
    }

    /**
     * @return iterable<string, array{bool, ClinicSlugCheck}>
     */
    public static function provideAWellFormedSlugIsTakenOrAvailableCases(): iterable
    {
        yield 'taken' => [true, ClinicSlugCheck::TAKEN];

        yield 'available' => [false, ClinicSlugCheck::AVAILABLE];
    }

    #[DataProvider('provideAMalformedSlugIsInvalidWithoutLookingCases')]
    public function testAMalformedSlugIsInvalidWithoutLooking(string $slug): void
    {
        $clinics = $this->createMock(ClinicRepositoryInterface::class);
        $clinics->expects(self::never())->method('existsBySlug');

        self::assertSame(ClinicSlugCheck::INVALID, new CheckClinicSlugHandler($clinics)(new CheckClinicSlug($slug)));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideAMalformedSlugIsInvalidWithoutLookingCases(): iterable
    {
        yield 'empty' => [''];

        yield 'capitals and spaces' => ['Clinique des Lilas'];

        yield 'accent' => ['clinique-éléphant'];
    }
}
