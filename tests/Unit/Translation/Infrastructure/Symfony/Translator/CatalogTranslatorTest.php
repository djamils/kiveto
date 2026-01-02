<?php

declare(strict_types=1);

namespace App\Tests\Unit\Translation\Infrastructure\Symfony\Translator;

use App\Shared\Application\Bus\QueryBusInterface;
use App\Translation\Application\Port\AppScopeResolverInterface;
use App\Translation\Application\Port\LocaleResolverInterface;
use App\Translation\Application\Query\GetTranslation\TranslationView;
use App\Translation\Infrastructure\Symfony\Translator\CatalogTranslator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Formatter\MessageFormatterInterface;
use Symfony\Component\Translation\MessageCatalogueInterface;

final class CatalogTranslatorTest extends TestCase
{
    public function testUsesQueryResult(): void
    {
        $fallback = new DummyTranslator();

        $queryBus = $this->createMock(QueryBusInterface::class);
        $queryBus->expects(self::once())
            ->method('ask')
            ->willReturn(new TranslationView('clinic', 'fr_FR', 'messages', 'hello', 'Bonjour'))
        ;

        $scopeResolver = $this->createStub(AppScopeResolverInterface::class);
        $scopeResolver->method('resolve')->willReturn(\App\Translation\Domain\ValueObject\AppScope::CLINIC);

        $localeResolver = $this->createStub(LocaleResolverInterface::class);
        $localeResolver->method('resolve')->willReturn(\App\Translation\Domain\ValueObject\Locale::fromString('fr_FR'));

        $formatter = $this->createStub(MessageFormatterInterface::class);
        $formatter->method('format')->willReturn('Bonjour');

        $translator = new CatalogTranslator($fallback, $queryBus, $scopeResolver, $localeResolver, $formatter);

        self::assertSame('Bonjour', $translator->trans('hello'));
    }

    public function testFallsBackToDefaultTranslator(): void
    {
        $fallback = new DummyTranslator(['hello' => 'fallback']);

        $queryBus = $this->createMock(QueryBusInterface::class);
        $queryBus->expects(self::once())
            ->method('ask')
            ->willReturn(null)
        ;

        $scopeResolver = $this->createStub(AppScopeResolverInterface::class);
        $scopeResolver->method('resolve')->willReturn(\App\Translation\Domain\ValueObject\AppScope::CLINIC);

        $localeResolver = $this->createStub(LocaleResolverInterface::class);
        $localeResolver->method('resolve')->willReturn(\App\Translation\Domain\ValueObject\Locale::fromString('fr_FR'));

        $formatter = $this->createStub(MessageFormatterInterface::class);
        $formatter->method('format')->willReturn('unused');

        $translator = new CatalogTranslator($fallback, $queryBus, $scopeResolver, $localeResolver, $formatter);

        self::assertSame('fallback', $translator->trans('hello'));
    }

    public function testTranslatorBagDelegation(): void
    {
        $fallback = new DummyTranslator(['hello' => 'fallback'], 'en');

        $queryBus       = $this->createStub(QueryBusInterface::class);
        $scopeResolver  = $this->createStub(AppScopeResolverInterface::class);
        $localeResolver = $this->createStub(LocaleResolverInterface::class);
        $formatter      = $this->createStub(MessageFormatterInterface::class);

        $translator = new CatalogTranslator($fallback, $queryBus, $scopeResolver, $localeResolver, $formatter);

        self::assertInstanceOf(MessageCatalogueInterface::class, $translator->getCatalogue('en'));
        $catalogues = $translator->getCatalogues();
        self::assertNotEmpty($catalogues);
    }

    public function testGetAndSetLocale(): void
    {
        $fallback = new DummyTranslator([], 'en');

        $queryBus       = $this->createStub(QueryBusInterface::class);
        $scopeResolver  = $this->createStub(AppScopeResolverInterface::class);
        $localeResolver = $this->createStub(LocaleResolverInterface::class);
        $localeResolver->method('resolve')->willReturn(\App\Translation\Domain\ValueObject\Locale::fromString('en'));
        $formatter = $this->createStub(MessageFormatterInterface::class);

        $translator = new CatalogTranslator($fallback, $queryBus, $scopeResolver, $localeResolver, $formatter);

        self::assertSame('en', $translator->getLocale());

        $translator->setLocale('fr');
        self::assertSame('fr', $fallback->getLocale());
    }

    public function testGetCatalogueThrowsWithoutTranslatorBag(): void
    {
        $fallback = new MinimalTranslator();

        $queryBus       = $this->createStub(QueryBusInterface::class);
        $scopeResolver  = $this->createStub(AppScopeResolverInterface::class);
        $localeResolver = $this->createStub(LocaleResolverInterface::class);
        $formatter      = $this->createStub(MessageFormatterInterface::class);

        $translator = new CatalogTranslator($fallback, $queryBus, $scopeResolver, $localeResolver, $formatter);

        $this->expectException(\LogicException::class);
        $translator->getCatalogue('fr');
    }

    public function testGetCataloguesThrowsWithoutTranslatorBag(): void
    {
        $fallback = new MinimalTranslator();

        $queryBus       = $this->createStub(QueryBusInterface::class);
        $scopeResolver  = $this->createStub(AppScopeResolverInterface::class);
        $localeResolver = $this->createStub(LocaleResolverInterface::class);
        $formatter      = $this->createStub(MessageFormatterInterface::class);

        $translator = new CatalogTranslator($fallback, $queryBus, $scopeResolver, $localeResolver, $formatter);

        $this->expectException(\LogicException::class);
        iterator_to_array($translator->getCatalogues());
    }
}
