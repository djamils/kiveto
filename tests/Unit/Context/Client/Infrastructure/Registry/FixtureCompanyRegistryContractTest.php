<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Infrastructure\Registry;

use App\Context\Client\Application\Port\CompanyRecord;
use App\Context\Client\Application\Port\CompanyRegistryInterface;
use App\Context\Client\Application\Port\CompanySummary;
use App\Context\Client\Infrastructure\Registry\FixtureCompanyRegistry;
use App\Shared\Application\ExternalApi\ExternalServiceUnavailable;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(FixtureCompanyRegistry::class)]
#[CoversClass(CompanyRecord::class)]
#[CoversClass(CompanySummary::class)]
final class FixtureCompanyRegistryContractTest extends CompanyRegistryContractTestCase
{
    public function testANameSearchIgnoresCaseAndAccents(): void
    {
        $hits = $this->registry()->search('ELEVAGE des calanques', 5);

        self::assertCount(1, $hits);
        self::assertSame('523456788', $hits[0]->siren);
        self::assertSame('Élevage des Calanques', $hits[0]->name);
        self::assertSame('EARL', $hits[0]->legalFormLabel);
        self::assertSame('01.45Z', $hits[0]->nafCode);
        self::assertFalse($hits[0]->isAssociation);
        self::assertTrue($hits[0]->isActive);
        self::assertNull($hits[0]->ceasedOn);
        self::assertSame('12 Chemin des Goudes', $hits[0]->addressLine);
        self::assertSame('13008', $hits[0]->postalCode);
        self::assertSame('Marseille', $hits[0]->city);
    }

    public function testANineDigitQueryIsTheExactSiren(): void
    {
        $hits = $this->registry()->search('775 612 344', 5);

        self::assertCount(1, $hits);
        self::assertTrue($hits[0]->isAssociation);
        self::assertSame('Association', $hits[0]->legalFormLabel);
        self::assertSame([], $this->registry()->search('000000000', 5));
    }

    public function testACeasedCompanyCarriesItsDate(): void
    {
        $record = $this->registry()->findBySiren('441569738');

        self::assertNotNull($record);
        self::assertFalse($record->isActive);
        self::assertFalse($record->isConfidential);
        self::assertSame('2025-12-31', $record->ceasedOn);
    }

    public function testAConfidentialCompanyIsKnownWithoutName(): void
    {
        $record = $this->registry()->findBySiren(FixtureCompanyRegistry::CONFIDENTIAL_SIREN);

        self::assertNotNull($record);
        self::assertTrue($record->isConfidential);
        self::assertNull($record->legalName);

        $hits = $this->registry()->search(FixtureCompanyRegistry::CONFIDENTIAL_SIREN, 5);
        self::assertSame('Entreprise non diffusible', $hits[0]->name);
        self::assertNull($hits[0]->addressLine);
        self::assertNull($hits[0]->postalCode);
        self::assertNull($hits[0]->city);
        self::assertSame([], $this->registry()->search('non diffusible', 5));
    }

    public function testTheOutageSirenIsDown(): void
    {
        try {
            $this->registry()->findBySiren(FixtureCompanyRegistry::UNAVAILABLE_SIREN);
            self::fail('An outage was expected.');
        } catch (ExternalServiceUnavailable $e) {
            self::assertFalse($e->isThrottled());
        }
    }

    public function testTheLimitQueryIsThrottled(): void
    {
        try {
            $this->registry()->search('Limite', 5);
            self::fail('A throttled answer was expected.');
        } catch (ExternalServiceUnavailable $e) {
            self::assertTrue($e->isThrottled());
        }
    }

    public function testAnEmptyQueryOrANegativeLimitFindsNothing(): void
    {
        self::assertSame([], $this->registry()->search('   ', 5));
        self::assertSame([], $this->registry()->search('calanques', -1));
    }

    protected function registry(): CompanyRegistryInterface
    {
        return new FixtureCompanyRegistry();
    }

    protected function registryInOutage(): CompanyRegistryInterface
    {
        return new class implements CompanyRegistryInterface {
            public function search(string $query, int $limit): array
            {
                return new FixtureCompanyRegistry()->search(FixtureCompanyRegistry::UNAVAILABLE_QUERY, $limit);
            }

            public function findBySiren(string $siren): never
            {
                throw new \LogicException('Not used.');
            }
        };
    }

    protected function knownSiret(): array
    {
        return ['52345678800018', '523456788'];
    }

    protected function queryWithActiveAndCeased(): string
    {
        return 'mont';
    }

    protected function knownSiren(): array
    {
        return ['812345676', 'Centre équestre du Lac'];
    }

    protected function unknownSiren(): string
    {
        return '000000000';
    }
}
