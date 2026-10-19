<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Infrastructure\Registry;

use App\Context\Client\Application\Port\CompanyRegistryInterface;
use App\Context\Client\Application\Port\CompanySummary;
use App\Shared\Application\ExternalApi\ExternalServiceUnavailable;
use PHPUnit\Framework\TestCase;

/**
 * What every CompanyRegistryInterface adapter guarantees, whatever answers it
 * (fixture data, recorded API responses).
 */
abstract class CompanyRegistryContractTestCase extends TestCase
{
    public function testAFourteenDigitQueryIsSearchedAsAHeadOfficeSiret(): void
    {
        [$siret, $siren] = $this->knownSiret();

        $hits = $this->registry()->search($siret, 5);

        self::assertCount(1, $hits);
        self::assertSame($siren, $hits[0]->siren);
        self::assertSame($siret, $hits[0]->headOfficeSiret);
    }

    public function testActiveCompaniesComeFirst(): void
    {
        $hits = $this->registry()->search($this->queryWithActiveAndCeased(), 10);

        $activity = array_map(static fn (CompanySummary $hit): bool => $hit->isActive, $hits);

        self::assertContains(true, $activity);
        self::assertContains(false, $activity);

        $activeFirst = $activity;
        rsort($activeFirst);
        self::assertSame($activeFirst, $activity);
    }

    public function testTheLimitIsRespected(): void
    {
        self::assertCount(1, $this->registry()->search($this->queryWithActiveAndCeased(), 1));
    }

    public function testAKnownSirenGivesItsRecord(): void
    {
        [$siren, $name] = $this->knownSiren();

        $record = $this->registry()->findBySiren($siren);

        self::assertNotNull($record);
        self::assertSame($siren, $record->siren);
        self::assertSame($name, $record->legalName);
    }

    public function testAnUnknownSirenGivesNull(): void
    {
        self::assertNull($this->registry()->findBySiren($this->unknownSiren()));
    }

    public function testAnOutageThrowsInsteadOfAnsweringNothing(): void
    {
        $this->expectException(ExternalServiceUnavailable::class);

        $this->registryInOutage()->search($this->queryWithActiveAndCeased(), 5);
    }

    abstract protected function registry(): CompanyRegistryInterface;

    abstract protected function registryInOutage(): CompanyRegistryInterface;

    /**
     * @return array{string, string} a head-office SIRET and the SIREN it belongs to
     */
    abstract protected function knownSiret(): array;

    /**
     * A name query matching at least one active and one ceased company.
     */
    abstract protected function queryWithActiveAndCeased(): string;

    /**
     * @return array{string, string} a SIREN the registry knows and its legal name
     */
    abstract protected function knownSiren(): array;

    abstract protected function unknownSiren(): string;
}
