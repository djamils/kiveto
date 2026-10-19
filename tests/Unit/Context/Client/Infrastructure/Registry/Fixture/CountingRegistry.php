<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Client\Infrastructure\Registry\Fixture;

use App\Context\Client\Application\Port\CompanyRecord;
use App\Context\Client\Application\Port\CompanyRegistryInterface;
use App\Context\Client\Infrastructure\Registry\FixtureCompanyRegistry;

/**
 * Fixture registry counting the calls that reach it.
 */
final class CountingRegistry implements CompanyRegistryInterface
{
    public int $searches = 0;
    public int $lookups  = 0;

    public function search(string $query, int $limit): array
    {
        ++$this->searches;

        return new FixtureCompanyRegistry()->search($query, $limit);
    }

    public function findBySiren(string $siren): ?CompanyRecord
    {
        ++$this->lookups;

        return new FixtureCompanyRegistry()->findBySiren($siren);
    }
}
