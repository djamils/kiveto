<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Infrastructure\Registry;

use App\Context\Client\Infrastructure\Registry\RechercheEntreprisesMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RechercheEntreprisesMapper::class)]
final class RechercheEntreprisesMapperTest extends TestCase
{
    public function testResultsWithoutAWellFormedSirenAreSkipped(): void
    {
        $mapper = new RechercheEntreprisesMapper();

        self::assertSame([], $mapper->results([]));
        self::assertSame([], $mapper->results(['results' => 'nope']));
        self::assertSame([['siren' => '523456788']], $mapper->results(['results' => [['siren' => '52345'], 'x', ['siren' => 523456788], ['siren' => '523456788']]]));
    }

    public function testMissingFieldsAreNull(): void
    {
        $summary = new RechercheEntreprisesMapper()->summary(['siren' => '523456788', 'nom_complet' => 'ELEVAGE DES CALANQUES']);

        self::assertSame('ELEVAGE DES CALANQUES', $summary->name);
        self::assertNull($summary->legalFormLabel);
        self::assertNull($summary->nafCode);
        self::assertFalse($summary->isAssociation);
        self::assertTrue($summary->isActive);
        self::assertNull($summary->ceasedOn);
        self::assertNull($summary->headOfficeSiret);
        self::assertNull($summary->addressLine);
        self::assertNull($summary->postalCode);
        self::assertNull($summary->city);
    }

    public function testTheLegalNameIsPreferredToTheFullName(): void
    {
        $record = new RechercheEntreprisesMapper()->record([
            'siren'              => '523456788',
            'nom_complet'        => 'ELEVAGE DES CALANQUES (EDC)',
            'nom_raison_sociale' => 'ELEVAGE DES CALANQUES',
            'nature_juridique'   => 6598,
        ]);

        self::assertSame('ELEVAGE DES CALANQUES', $record->legalName);
    }

    public function testAMaskedNameIsConfidentialWhateverTheDiffusionStatus(): void
    {
        $record = new RechercheEntreprisesMapper()->record(['siren' => '900000019', 'nom_complet' => '[NON-DIFFUSIBLE]', 'statut_diffusion' => 'O']);

        self::assertTrue($record->isConfidential);
        self::assertNull($record->legalName);
    }

    public function testAMalformedClosingDateIsIgnored(): void
    {
        $summary = new RechercheEntreprisesMapper()->summary(['siren' => '441569738', 'etat_administratif' => 'C', 'date_fermeture' => '31/12/2025']);

        self::assertFalse($summary->isActive);
        self::assertNull($summary->ceasedOn);
    }

    public function testAnAddressWithoutItsCityIsKeptWhole(): void
    {
        $mapper = new RechercheEntreprisesMapper();

        self::assertSame('ROUTE DU LAC', $mapper->summary(['siren' => '812345676', 'siege' => ['adresse' => 'ROUTE DU LAC']])->addressLine);
        self::assertNull($mapper->summary(['siren' => '812345676', 'siege' => ['adresse' => '95880 ENGHIEN', 'code_postal' => '95880', 'libelle_commune' => 'ENGHIEN']])->addressLine);
        self::assertSame('1 RUE X', $mapper->summary(['siren' => '812345676', 'siege' => ['adresse' => '1 RUE X 95880', 'code_postal' => '95880']])->addressLine);
    }

    public function testAnAssociation(): void
    {
        self::assertTrue(new RechercheEntreprisesMapper()->summary(['siren' => '775612344', 'complements' => ['est_association' => true]])->isAssociation);
    }
}
