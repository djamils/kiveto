<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\Procurement\Infrastructure\Adapter\Catalog;

use App\Context\Procurement\Domain\Shared\ValueObject\ArticleId;
use App\Context\Procurement\Domain\Shared\ValueObject\ClinicId;
use App\Context\Procurement\Infrastructure\Adapter\Catalog\CatalogArticleProviderAdapter;
use App\Shared\Application\Bus\QueryBusInterface;
use PHPUnit\Framework\TestCase;

final class CatalogArticleProviderAdapterTest extends TestCase
{
    private const string ARTICLE_UUID = '01932b00-0000-7000-8000-000000000200';
    private const string CLINIC_UUID  = '01932b00-0000-7000-8000-000000000003';

    public function testExistsReturnsTrue(): void
    {
        $adapter = new CatalogArticleProviderAdapter($this->createStub(QueryBusInterface::class));

        self::assertTrue($adapter->exists(
            ArticleId::fromString(self::ARTICLE_UUID),
            ClinicId::fromString(self::CLINIC_UUID),
        ));
    }

    public function testIsActiveReturnsTrue(): void
    {
        $adapter = new CatalogArticleProviderAdapter($this->createStub(QueryBusInterface::class));

        self::assertTrue($adapter->isActive(
            ArticleId::fromString(self::ARTICLE_UUID),
            ClinicId::fromString(self::CLINIC_UUID),
        ));
    }
}
