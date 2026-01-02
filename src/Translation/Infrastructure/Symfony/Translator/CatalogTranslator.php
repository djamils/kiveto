<?php

declare(strict_types=1);

namespace App\Translation\Infrastructure\Symfony\Translator;

use App\Shared\Application\Bus\QueryBusInterface;
use App\Translation\Application\Port\AppScopeResolverInterface;
use App\Translation\Application\Port\LocaleResolverInterface;
use App\Translation\Application\Query\GetTranslation\GetTranslation;
use App\Translation\Application\Query\GetTranslation\TranslationView;
use Symfony\Component\Translation\Formatter\MessageFormatterInterface;
use Symfony\Component\Translation\MessageCatalogueInterface;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class CatalogTranslator implements TranslatorInterface, LocaleAwareInterface, TranslatorBagInterface
{
    public function __construct(
        private readonly TranslatorInterface $fallbackTranslator,
        private readonly QueryBusInterface $queryBus,
        private readonly AppScopeResolverInterface $scopeResolver,
        private readonly LocaleResolverInterface $localeResolver,
        private readonly MessageFormatterInterface $formatter,
    ) {
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
    {
        $resolvedLocale = $locale ?? $this->localeResolver->resolve()->toString();
        $resolvedDomain = $domain ?? 'messages';

        dump($id);

        /** @var TranslationView|null $translation */
        $translation = $this->queryBus->ask(
            new GetTranslation(
                $this->scopeResolver->resolve()->value,
                $resolvedLocale,
                $resolvedDomain,
                $id,
            ),
        );

        if ($translation instanceof TranslationView) {
            return $this->formatter->format($translation->value, $resolvedLocale, $parameters);
        }

        return $this->fallbackTranslator->trans($id, $parameters, $domain, $locale);
    }

    public function getLocale(): string
    {
        return $this->localeResolver->resolve()->toString();
    }

    public function setLocale(string $locale): void
    {
        if ($this->fallbackTranslator instanceof LocaleAwareInterface) {
            $this->fallbackTranslator->setLocale($locale);
        }
    }

    public function getCatalogue(string $locale = null): MessageCatalogueInterface
    {
        if (!$this->fallbackTranslator instanceof TranslatorBagInterface) {
            throw new \LogicException('Fallback translator must implement TranslatorBagInterface.');
        }

        $resolvedLocale = $locale ?? $this->localeResolver->resolve()->toString();

        return $this->fallbackTranslator->getCatalogue($resolvedLocale);
    }

    /**
     * @return array<MessageCatalogueInterface>
     */
    public function getCatalogues(): array
    {
        if (!$this->fallbackTranslator instanceof TranslatorBagInterface) {
            throw new \LogicException('Fallback translator must implement TranslatorBagInterface.');
        }

        return $this->fallbackTranslator->getCatalogues();
    }
}
