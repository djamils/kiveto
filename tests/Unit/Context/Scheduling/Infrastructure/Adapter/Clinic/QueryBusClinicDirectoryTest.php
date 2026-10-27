<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Scheduling\Infrastructure\Adapter\Clinic;

use App\Context\Clinic\Application\Query\Clinic\GetClinic\ClinicDto;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\GetClinic;
use App\Context\Clinic\Application\Query\Clinic\GetClinic\LetterheadDto;
use App\Context\Clinic\Application\Query\Staff\GetStaffPrintedName\GetStaffPrintedName;
use App\Context\Clinic\Domain\ValueObject\ClinicStatus;
use App\Context\Scheduling\Infrastructure\Adapter\Clinic\QueryBusClinicDirectory;
use App\Tests\Support\AnsweringQueryBus;
use PHPUnit\Framework\TestCase;

final class QueryBusClinicDirectoryTest extends TestCase
{
    public function testTheClinicIsReadFromItsQuery(): void
    {
        $bus = new AnsweringQueryBus([
            GetClinic::class           => self::clinic(new LetterheadDto(null, null, null, null, null, null, null, null, null, ['18 rue Lepic', '75018 Paris'], 'Paris', '+33142641800', 'accueil@clinique-montmartre.fr', null)),
            GetStaffPrintedName::class => static fn (GetStaffPrintedName $q): ?string => 'user-1' === $q->userId ? 'Dr Claire Rousseau' : null,
        ]);
        $directory = new QueryBusClinicDirectory($bus, 'mail.kiveto.fr');

        $card = $directory->clinic('clinic-1');
        self::assertSame(['Clinique Montmartre', 'America/Cayenne', 'accueil@clinique-montmartre.fr', '18 rue Lepic, 75018 Paris'], [$card->name, $card->timeZone->getName(), $card->email, $card->address]);
        self::assertSame('Dr Claire Rousseau', $directory->printedName('clinic-1', 'user-1'));
        self::assertNull($directory->printedName('clinic-1', 'user-2'));
    }

    public function testAClinicWithoutLetterheadIsTheOrganizerFromItsSendingAddress(): void
    {
        $card = new QueryBusClinicDirectory(new AnsweringQueryBus([GetClinic::class => self::clinic(null)]), 'mail.kiveto.fr')->clinic('clinic-1');

        // Its messages come from <slug>@<notification domain>: the calendar invitations name it as organizer.
        self::assertSame('clinique-montmartre@mail.kiveto.fr', $card->email);
        self::assertNull($card->address);
    }

    public function testAnUnknownClinicIsRefused(): void
    {
        $this->expectException(\DomainException::class);

        new QueryBusClinicDirectory(new AnsweringQueryBus(), 'mail.kiveto.fr')->clinic('clinic-1');
    }

    private static function clinic(?LetterheadDto $letterhead): ClinicDto
    {
        return new ClinicDto('clinic-1', 'Clinique Montmartre', 'clinique-montmartre', 'America/Cayenne', 'fr-FR', 'FR', null, 'EUR', ClinicStatus::ACTIVE, null, '2026-01-01', '2026-01-01', $letterhead);
    }
}
