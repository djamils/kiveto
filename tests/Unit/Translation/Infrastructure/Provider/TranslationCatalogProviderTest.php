<?php

declare(strict_types=1);

namespace App\Tests\Unit\Translation\Infrastructure\Provider;

use App\Translation\Application\Port\CatalogCacheInterface;
use App\Translation\Domain\Repository\TranslationSearchRepository;
use App\Translation\Domain\ValueObject\AppScope;
use App\Translation\Domain\ValueObject\Locale;
use App\Translation\Domain\ValueObject\TranslationCatalogId;
use App\Translation\Domain\ValueObject\TranslationDomain;
use App\Translation\Infrastructure\Provider\TranslationCatalogProvider;
use PHPUnit\Framework\TestCase;

final class TranslationCatalogProviderTest extends TestCase
{
    public function testGetEffectiveCatalogForSharedScope(): void
    {
        $repository = $this->createMock(TranslationSearchRepository::class);
        $cache      = $this->createMock(CatalogCacheInterface::class);

        $scope  = AppScope::SHARED;
        $locale = Locale::fromString('fr');
        $domain = TranslationDomain::fromString('messages');

        $sharedCatalog = ['key1' => 'value1', 'key2' => 'value2'];

        $cache->expects(self::once())
            ->method('get')
            ->willReturn(null)
        ;

        $repository->expects(self::once())
            ->method('findCatalog')
            ->with($scope, $locale, $domain)
            ->willReturn($sharedCatalog)
        ;

        $cache->expects(self::once())
            ->method('save')
            ->with(
                self::callback(static fn (TranslationCatalogId $id): bool => $id->scope() === $scope
                    && 'fr' === $id->locale()->toString()
                    && 'messages' === $id->domain()->toString()),
                $sharedCatalog,
                3600,
            )
        ;

        $provider = new TranslationCatalogProvider($repository, $cache);

        $result = $provider->getEffectiveCatalog($scope, $locale, 'messages');

        self::assertSame($sharedCatalog, $result);
    }

    public function testGetEffectiveCatalogForNonSharedScopeMergesWithShared(): void
    {
        $repository = $this->createMock(TranslationSearchRepository::class);
        $cache      = $this->createMock(CatalogCacheInterface::class);

        $scope  = AppScope::CLINIC;
        $locale = Locale::fromString('fr');
        $domain = TranslationDomain::fromString('messages');

        $clinicCatalog = ['key1' => 'clinic_value1', 'key3' => 'clinic_value3'];
        $sharedCatalog = ['key1' => 'shared_value1', 'key2' => 'shared_value2'];

        $cache->expects(self::exactly(2))
            ->method('get')
            ->willReturn(null)
        ;

        $repository->expects(self::exactly(2))
            ->method('findCatalog')
            ->willReturnCallback(static function (AppScope $s) use ($clinicCatalog, $sharedCatalog): array {
                return AppScope::CLINIC === $s ? $clinicCatalog : $sharedCatalog;
            })
        ;

        $provider = new TranslationCatalogProvider($repository, $cache);

        $result = $provider->getEffectiveCatalog($scope, $locale, 'messages');

        self::assertSame('clinic_value1', $result['key1']);
        self::assertSame('shared_value2', $result['key2']);
        self::assertSame('clinic_value3', $result['key3']);
    }

    public function testGetEffectiveCatalogUsesInMemoryCache(): void
    {
        $repository = $this->createMock(TranslationSearchRepository::class);
        $cache      = $this->createMock(CatalogCacheInterface::class);

        $scope  = AppScope::SHARED;
        $locale = Locale::fromString('en');
        $domain = TranslationDomain::fromString('validators');

        $catalog = ['error.required' => 'This field is required'];

        $cache->expects(self::once())
            ->method('get')
            ->willReturn(null)
        ;

        $repository->expects(self::once())
            ->method('findCatalog')
            ->willReturn($catalog)
        ;

        $provider = new TranslationCatalogProvider($repository, $cache);

        $result1 = $provider->getEffectiveCatalog($scope, $locale, 'validators');
        $result2 = $provider->getEffectiveCatalog($scope, $locale, 'validators');

        self::assertSame($result1, $result2);
        self::assertSame($catalog, $result1);
    }

    public function testGetEffectiveCatalogLoadsFromCache(): void
    {
        $repository = $this->createMock(TranslationSearchRepository::class);
        $cache      = $this->createMock(CatalogCacheInterface::class);

        $scope  = AppScope::PORTAL;
        $locale = Locale::fromString('de_DE');
        $domain = TranslationDomain::fromString('emails');

        $cachedPortalCatalog = ['subject.welcome' => 'Willkommen'];
        $cachedSharedCatalog = ['footer.copyright' => 'Copyright 2025'];

        $cache->expects(self::exactly(2))
            ->method('get')
            ->willReturnOnConsecutiveCalls($cachedPortalCatalog, $cachedSharedCatalog)
        ;

        $repository->expects(self::never())
            ->method('findCatalog')
        ;

        $provider = new TranslationCatalogProvider($repository, $cache);

        $result = $provider->getEffectiveCatalog($scope, $locale, 'emails');

        self::assertSame('Willkommen', $result['subject.welcome']);
        self::assertSame('Copyright 2025', $result['footer.copyright']);
    }

    public function testResetClearsInMemoryCaches(): void
    {
        $repository = $this->createMock(TranslationSearchRepository::class);
        $cache      = $this->createMock(CatalogCacheInterface::class);

        $scope  = AppScope::BACKOFFICE;
        $locale = Locale::fromString('fr_FR');
        $domain = TranslationDomain::fromString('menu');

        $backofficeCatalog = ['dashboard' => 'Tableau de bord'];
        $sharedCatalog     = ['logout' => 'Déconnexion'];

        $cache->expects(self::exactly(4))
            ->method('get')
            ->willReturn(null)
        ;

        $repository->expects(self::exactly(4))
            ->method('findCatalog')
            ->willReturnCallback(static function (AppScope $s) use ($backofficeCatalog, $sharedCatalog): array {
                return AppScope::BACKOFFICE === $s ? $backofficeCatalog : $sharedCatalog;
            })
        ;

        $provider = new TranslationCatalogProvider($repository, $cache);

        $provider->getEffectiveCatalog($scope, $locale, 'menu');
        $provider->reset();
        $provider->getEffectiveCatalog($scope, $locale, 'menu');
    }

    public function testCustomTtlIsUsedForCacheSave(): void
    {
        $repository = $this->createMock(TranslationSearchRepository::class);
        $cache      = $this->createMock(CatalogCacheInterface::class);

        $customTtl = 7200;

        $scope  = AppScope::SHARED;
        $locale = Locale::fromString('es');
        $domain = TranslationDomain::fromString('forms');

        $catalog = ['submit' => 'Enviar'];

        $cache->expects(self::once())
            ->method('get')
            ->willReturn(null)
        ;

        $repository->expects(self::once())
            ->method('findCatalog')
            ->willReturn($catalog)
        ;

        $cache->expects(self::once())
            ->method('save')
            ->with(
                self::anything(),
                $catalog,
                $customTtl,
            )
        ;

        $provider = new TranslationCatalogProvider($repository, $cache, $customTtl);

        $provider->getEffectiveCatalog($scope, $locale, 'forms');
    }

    public function testMultipleScopesUseDifferentCacheKeys(): void
    {
        $repository = $this->createMock(TranslationSearchRepository::class);
        $cache      = $this->createMock(CatalogCacheInterface::class);

        $locale = Locale::fromString('en');
        $domain = TranslationDomain::fromString('actions');

        $clinicCatalog     = ['save' => 'Save patient'];
        $portalCatalog     = ['save' => 'Save profile'];
        $backofficeCatalog = ['save' => 'Save settings'];
        $sharedCatalog     = ['cancel' => 'Cancel'];

        $cache->expects(self::exactly(4))
            ->method('get')
            ->willReturn(null)
        ;

        $repository->expects(self::exactly(4))
            ->method('findCatalog')
            ->willReturnCallback(
                static function (AppScope $scope) use (
                    $clinicCatalog,
                    $portalCatalog,
                    $backofficeCatalog,
                    $sharedCatalog,
                ): array {
                    return match ($scope) {
                        AppScope::CLINIC     => $clinicCatalog,
                        AppScope::PORTAL     => $portalCatalog,
                        AppScope::BACKOFFICE => $backofficeCatalog,
                        AppScope::SHARED     => $sharedCatalog,
                    };
                },
            )
        ;

        $provider = new TranslationCatalogProvider($repository, $cache);

        $clinicResult     = $provider->getEffectiveCatalog(AppScope::CLINIC, $locale, 'actions');
        $portalResult     = $provider->getEffectiveCatalog(AppScope::PORTAL, $locale, 'actions');
        $backofficeResult = $provider->getEffectiveCatalog(AppScope::BACKOFFICE, $locale, 'actions');

        self::assertSame('Save patient', $clinicResult['save']);
        self::assertSame('Save profile', $portalResult['save']);
        self::assertSame('Save settings', $backofficeResult['save']);
        self::assertSame('Cancel', $clinicResult['cancel']);
        self::assertSame('Cancel', $portalResult['cancel']);
        self::assertSame('Cancel', $backofficeResult['cancel']);
    }
}
