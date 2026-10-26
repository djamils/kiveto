<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Clinic\Application\Query\Clinic\GetClinic;

use App\Context\Clinic\Application\Query\Clinic\GetClinic\ClinicDto;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\LetterheadDto;
use App\Context\Clinic\Domain\ValueObject\ClinicStatus;
use App\Context\Clinic\Domain\ValueObject\Letterhead;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ClinicDto::class)]
final class ClinicDtoTest extends TestCase
{
    public function testTheDocumentClinicCarriesNameColourLogoAndLetterhead(): void
    {
        $document = self::clinic(LetterheadDto::fromDomain(Letterhead::create(legalName: 'SELARL Montmartre')))->toDocumentClinic();

        self::assertSame('Clinique Montmartre', $document->name);
        self::assertSame('#4f86c6', $document->brandColor);
        self::assertSame('clinic/logo.png', $document->logoKey);
        self::assertSame('SELARL Montmartre', $document->letterhead?->legalName);
    }

    public function testAClinicReadWithoutLetterheadPrintsNone(): void
    {
        self::assertNull(self::clinic(null)->toDocumentClinic()->letterhead);
    }

    private static function clinic(?LetterheadDto $letterhead): ClinicDto
    {
        return new ClinicDto(
            id: '01960000-0000-7000-8000-000000000001',
            name: 'Clinique Montmartre',
            slug: 'clinique-montmartre',
            timeZone: 'Europe/Paris',
            locale: 'fr-FR',
            countryCode: 'FR',
            jurisdictionCode: null,
            currencyCode: 'EUR',
            status: ClinicStatus::ACTIVE,
            clinicGroupId: null,
            createdAt: '2026-01-01T00:00:00+00:00',
            updatedAt: '2026-01-01T00:00:00+00:00',
            letterhead: $letterhead,
            brandColor: '#4f86c6',
            logoKey: 'clinic/logo.png',
        );
    }
}
