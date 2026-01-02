<?php

declare(strict_types=1);

namespace App\Tests\Unit\Translation\Domain\ValueObject;

use App\Translation\Domain\ValueObject\AppScope;
use App\Translation\Domain\ValueObject\Locale;
use App\Translation\Domain\ValueObject\TranslationCatalogId;
use App\Translation\Domain\ValueObject\TranslationDomain;
use PHPUnit\Framework\TestCase;

final class TranslationCatalogIdTest extends TestCase
{
    public function testCacheKeyPart(): void
    {
        $id = new TranslationCatalogId(
            AppScope::CLINIC,
            Locale::fromString('fr_FR'),
            TranslationDomain::fromString('messages'),
        );

        self::assertSame('clinic:fr_FR:messages', $id->cacheKeyPart());
    }

    public function testFromStrings(): void
    {
        $id = TranslationCatalogId::fromStrings('portal', 'en_GB', 'auth');

        self::assertSame('portal', $id->scope()->value);
        self::assertSame('en_GB', $id->locale()->toString());
        self::assertSame('auth', $id->domain()->toString());
    }
}
