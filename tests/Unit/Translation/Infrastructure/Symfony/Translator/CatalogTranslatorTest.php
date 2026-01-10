<?php

declare(strict_types=1);

namespace App\Tests\Unit\Translation\Infrastructure\Symfony\Translator;

use App\Shared\Domain\Localization\Locale as DomainLocale;
use App\Translation\Application\Port\AppScopeResolverInterface;
use App\Translation\Application\Port\LocaleResolverInterface;
use App\Translation\Domain\ValueObject\AppScope;
use App\Translation\Infrastructure\Provider\TranslationCatalogProvider;
use App\Translation\Infrastructure\Symfony\Translator\CatalogTranslator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Formatter\MessageFormatterInterface;
use Symfony\Component\Translation\MessageCatalogueInterface;

final class CatalogTranslatorTest extends TestCase
{
    public function testUsesCatalogProvider(): void
    {
        $fallback = new DummyTranslator();

        $provider = $this->createMock(TranslationCatalogProvider::class);
        $provider->expects(self::once())
            ->method('getEffectiveCatalog')
            ->willReturn(['hello' => 'Bonjour'])
        ;

        $scopeResolver = $this->createStub(AppScopeResolverInterface::class);
        $scopeResolver->method('resolve')->willReturn(AppScope::CLINIC);

        $localeResolver = $this->createStub(LocaleResolverInterface::class);
        $localeResolver->method('resolve')->willReturn(DomainLocale::fromString('fr-FR'));

        $formatter = $this->createStub(MessageFormatterInterface::class);
        $formatter->method('format')->willReturn('Bonjour');

        $translator = new CatalogTranslator($fallback, $provider, $scopeResolver, $localeResolver, $formatter);

        self::assertSame('Bonjour', $translator->trans('hello'));
    }

    public function testFallsBackToDefaultTranslator(): void
    {
        $fallback = new DummyTranslator(['hello' => 'fallback']);

        $provider = $this->createMock(TranslationCatalogProvider::class);
        $provider->expects(self::once())
            ->method('getEffectiveCatalog')
            ->willReturn([])
        ;

        $scopeResolver = $this->createStub(AppScopeResolverInterface::class);
        $scopeResolver->method('resolve')->willReturn(AppScope::CLINIC);

        $localeResolver = $this->createStub(LocaleResolverInterface::class);
        $localeResolver->method('resolve')->willReturn(DomainLocale::fromString('fr-FR'));

        $formatter = $this->createStub(MessageFormatterInterface::class);
        $formatter->method('format')->willReturn('unused');

        $translator = new CatalogTranslator($fallback, $provider, $scopeResolver, $localeResolver, $formatter);

        self::assertSame('fallback', $translator->trans('hello'));
    }

    public function testTranslatorBagDelegation(): void
    {
        $fallback = new DummyTranslator(['hello' => 'fallback'], 'en');

        $provider       = $this->createStub(TranslationCatalogProvider::class);
        $scopeResolver  = $this->createStub(AppScopeResolverInterface::class);
        $localeResolver = $this->createStub(LocaleResolverInterface::class);
        $formatter      = $this->createStub(MessageFormatterInterface::class);

        $translator = new CatalogTranslator($fallback, $provider, $scopeResolver, $localeResolver, $formatter);

        self::assertInstanceOf(MessageCatalogueInterface::class, $translator->getCatalogue('en'));
        $catalogues = $translator->getCatalogues();
        self::assertNotEmpty($catalogues);
    }

    public function testGetAndSetLocale(): void
    {
        $fallback = new DummyTranslator([], 'en-GB');

        $provider       = $this->createStub(TranslationCatalogProvider::class);
        $scopeResolver  = $this->createStub(AppScopeResolverInterface::class);
        $localeResolver = $this->createStub(LocaleResolverInterface::class);
        $localeResolver->method('resolve')->willReturn(DomainLocale::fromString('en-GB'));
        $formatter = $this->createStub(MessageFormatterInterface::class);

        $translator = new CatalogTranslator($fallback, $provider, $scopeResolver, $localeResolver, $formatter);

        self::assertSame('en-GB', $translator->getLocale());

        $translator->setLocale('fr-FR');
        self::assertSame('fr-FR', $fallback->getLocale());
    }

    public function testGetCatalogueThrowsWithoutTranslatorBag(): void
    {
        $fallback = new MinimalTranslator();

        $provider       = $this->createStub(TranslationCatalogProvider::class);
        $scopeResolver  = $this->createStub(AppScopeResolverInterface::class);
        $localeResolver = $this->createStub(LocaleResolverInterface::class);
        $formatter      = $this->createStub(MessageFormatterInterface::class);

        $translator = new CatalogTranslator($fallback, $provider, $scopeResolver, $localeResolver, $formatter);

        $this->expectException(\LogicException::class);
        $translator->getCatalogue('fr');
    }

    public function testGetCataloguesThrowsWithoutTranslatorBag(): void
    {
        $fallback = new MinimalTranslator();

        $provider       = $this->createStub(TranslationCatalogProvider::class);
        $scopeResolver  = $this->createStub(AppScopeResolverInterface::class);
        $localeResolver = $this->createStub(LocaleResolverInterface::class);
        $formatter      = $this->createStub(MessageFormatterInterface::class);

        $translator = new CatalogTranslator($fallback, $provider, $scopeResolver, $localeResolver, $formatter);

        $this->expectException(\LogicException::class);
        iterator_to_array($translator->getCatalogues());
    }

    public function testGetCatalogueMergesTouchedDomains(): void
    {
        $fallback = new DummyTranslator(['hello' => 'fallback'], 'fr-FR');

        $provider = $this->createStub(TranslationCatalogProvider::class);
        $provider->method('getEffectiveCatalog')->willReturn(['hello' => 'from-db']);

        $scopeResolver = $this->createStub(AppScopeResolverInterface::class);
        $scopeResolver->method('resolve')->willReturn(AppScope::CLINIC);

        $localeResolver = $this->createStub(LocaleResolverInterface::class);
        $localeResolver->method('resolve')->willReturn(DomainLocale::fromString('fr-FR'));

        $formatter = $this->createStub(MessageFormatterInterface::class);
        $formatter->method('format')->willReturn('from-db');

        $translator = new CatalogTranslator($fallback, $provider, $scopeResolver, $localeResolver, $formatter);

        $translator->trans('hello'); // touch domain
        $catalogue = $translator->getCatalogue('fr-FR');

        self::assertSame('from-db', $catalogue->get('hello', 'messages'));
    }

    public function testResetClearsTouchedDomains(): void
    {
        $fallback = new DummyTranslator(['hello' => 'fallback'], 'fr-FR');

        $provider = $this->createStub(TranslationCatalogProvider::class);
        $provider->method('getEffectiveCatalog')->willReturn(['hello' => 'from-db']);

        $scopeResolver = $this->createStub(AppScopeResolverInterface::class);
        $scopeResolver->method('resolve')->willReturn(AppScope::CLINIC);

        $localeResolver = $this->createStub(LocaleResolverInterface::class);
        $localeResolver->method('resolve')->willReturn(DomainLocale::fromString('fr-FR'));

        $formatter = $this->createStub(MessageFormatterInterface::class);
        $formatter->method('format')->willReturn('from-db');

        $translator = new CatalogTranslator($fallback, $provider, $scopeResolver, $localeResolver, $formatter);

        $translator->trans('hello'); // touch domain
        $translator->reset();        // clear touched domains
        $catalogue = $translator->getCatalogue('fr-FR');

        self::assertSame('fallback', $catalogue->get('hello', 'messages'));
    }
}
