<?php

declare(strict_types=1);

namespace App\Translation\Infrastructure\Symfony\Translator;

use App\Translation\Application\Port\AppScopeResolverInterface;
use App\Translation\Application\Port\LocaleResolverInterface;
use App\Translation\Domain\ValueObject\Locale as DomainLocale;
use App\Translation\Infrastructure\Provider\TranslationCatalogProvider;
use Symfony\Component\Translation\Formatter\MessageFormatterInterface;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\MessageCatalogueInterface;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\Service\ResetInterface;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class CatalogTranslator implements
    TranslatorInterface,
    LocaleAwareInterface,
    TranslatorBagInterface,
    ResetInterface
{
    /** @var array<string, true> */
    private array $touchedDomains = [];

    public function __construct(
        private readonly TranslatorInterface $fallbackTranslator,
        private readonly TranslationCatalogProvider $catalogProvider,
        private readonly AppScopeResolverInterface $scopeResolver,
        private readonly LocaleResolverInterface $localeResolver,
        private readonly MessageFormatterInterface $formatter,
    ) {
    }

    /**
     * Clears request-scoped state to avoid leaking debug/profiler-related data across requests
     * in long-running runtimes.
     */
    public function reset(): void
    {
        // Track domains only for the current request.
        $this->touchedDomains = [];
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
    {
        $resolvedLocale = $locale ?? $this->localeResolver->resolve()->toString();
        $resolvedDomain = $domain ?? 'messages';

        $this->touchedDomains[$resolvedDomain] = true;

        $scope = $this->scopeResolver->resolve();
        $loc   = DomainLocale::fromString($resolvedLocale);

        $catalog = $this->catalogProvider->getEffectiveCatalog($scope, $loc, $resolvedDomain);

        if (isset($catalog[$id])) {
            return $this->formatter->format($catalog[$id], $resolvedLocale, $parameters);
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

    public function getCatalogue(?string $locale = null): MessageCatalogueInterface
    {
        if (!$this->fallbackTranslator instanceof TranslatorBagInterface) {
            throw new \LogicException('Fallback translator must implement TranslatorBagInterface.');
        }

        $resolvedLocale    = $locale ?? $this->localeResolver->resolve()->toString();
        $fallbackCatalogue = $this->fallbackTranslator->getCatalogue($resolvedLocale);

        if ([] === $this->touchedDomains) {
            return $fallbackCatalogue;
        }

        $catalogue = new MessageCatalogue($resolvedLocale);

        foreach ($fallbackCatalogue->all() as $domain => $messages) {
            /** @var array<string, string> $messages */
            foreach ($messages as $key => $value) {
                $catalogue->set($key, $value, $domain);
            }
        }

        $scope = $this->scopeResolver->resolve();
        $loc   = DomainLocale::fromString($resolvedLocale);

        foreach (array_keys($this->touchedDomains) as $domain) {
            $effective = $this->catalogProvider->getEffectiveCatalog($scope, $loc, $domain);
            foreach ($effective as $key => $rawValue) {
                $catalogue->set($key, $rawValue, $domain);
            }
        }

        return $catalogue;
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
