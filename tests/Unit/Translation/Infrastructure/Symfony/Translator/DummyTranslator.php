<?php

declare(strict_types=1);

namespace App\Tests\Unit\Translation\Infrastructure\Symfony\Translator;

use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\MessageCatalogueInterface;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Simple stub translator implementing required interfaces.
 */
final class DummyTranslator implements TranslatorInterface, TranslatorBagInterface, LocaleAwareInterface
{
    private string $locale;

    /**
     * @param array<string, string> $messages
     */
    public function __construct(private array $messages = [], string $locale = 'fr-FR')
    {
        $this->locale = $locale;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
    {
        $loc = $locale ?? $this->locale;

        return $this->messages[$id] ?? $id . '@' . $loc;
    }

    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function getCatalogue(?string $locale = null): MessageCatalogueInterface
    {
        $loc = $locale ?? $this->locale;

        return new MessageCatalogue($loc, ['messages' => $this->messages]);
    }

    /**
     * @return array<MessageCatalogueInterface>
     */
    public function getCatalogues(): array
    {
        return [$this->getCatalogue($this->locale)];
    }
}
