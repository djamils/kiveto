<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\ViewModel\Billing;

use App\Context\Billing\Domain\ValueObject\ReadinessRequirement;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * What a professional invoice still lacks before it can be numbered, as the
 * desk reads it: one French line per missing item, with where to fix it.
 * Buyer items lead to the client's sheet (the card to open), seller items to
 * the clinic's settings; both carry the `_returnTo` target that brings the
 * desk back (see ReturnToResolver). Without a client on file (a newcomer at
 * the counter), buyer items have no link: there is no sheet to correct yet.
 */
final readonly class ReadinessMessages
{
    public const string FLASH = 'readiness';

    private const string FIX_CLIENT   = 'Corriger la fiche';
    private const string FIX_SETTINGS = 'Paramètres';

    public function __construct(
        private UrlGeneratorInterface $urls,
    ) {
    }

    /**
     * @param list<ReadinessRequirement> $requirements
     * @param string                     $returnTo     a ReturnToResolver target, e.g. `clinic_checkout|admissionId=…`
     *
     * @return list<array{text: string, href: string|null, label: string|null}>
     */
    public function for(array $requirements, ?string $clientId, string $returnTo): array
    {
        $messages = [];

        foreach ($requirements as $requirement) {
            [$href, $label] = $this->link($requirement, $clientId, $returnTo);

            $messages[] = ['text' => self::text($requirement), 'href' => $href, 'label' => $label];
        }

        return $messages;
    }

    /**
     * The structured flash a checkout leaves when the invoice was refused:
     * the draft it is about and its messages, as JSON.
     *
     * @param list<array{text: string, href: string|null, label: string|null}> $messages
     */
    public static function flash(Request $request, string $invoiceId, array $messages): void
    {
        $session = $request->getSession();
        \assert($session instanceof FlashBagAwareSessionInterface);

        $session->getFlashBag()->set(self::FLASH, (string) json_encode(['invoiceId' => $invoiceId, 'items' => $messages]));
    }

    /**
     * Takes the structured flash back for the sheet of that invoice, so that
     * the layout does not show it as a toast; empty when it is about another
     * document or absent.
     *
     * @return list<array{text: string, href: string|null, label: string|null}>
     */
    public static function takeFlash(Request $request, string $invoiceId): array
    {
        if (!$request->hasSession()) {
            return [];
        }

        $session = $request->getSession();
        \assert($session instanceof FlashBagAwareSessionInterface);

        $items = [];

        foreach ($session->getFlashBag()->get(self::FLASH) as $raw) {
            $data = \is_string($raw) ? json_decode($raw, true) : null;

            if (!\is_array($data) || ($data['invoiceId'] ?? null) !== $invoiceId || !\is_array($data['items'] ?? null)) {
                continue;
            }

            foreach ($data['items'] as $item) {
                if (\is_array($item) && \is_string($item['text'] ?? null)) {
                    $items[] = [
                        'text'  => $item['text'],
                        'href'  => \is_string($item['href'] ?? null) ? $item['href'] : null,
                        'label' => \is_string($item['label'] ?? null) ? $item['label'] : null,
                    ];
                }
            }
        }

        return $items;
    }

    public static function text(ReadinessRequirement $requirement): string
    {
        return match ($requirement) {
            ReadinessRequirement::BUYER_LEGAL_NAME            => 'Raison sociale du client manquante',
            ReadinessRequirement::BUYER_SIREN                 => 'SIREN du client manquant',
            ReadinessRequirement::BUYER_ADDRESS               => 'Adresse du client incomplète',
            ReadinessRequirement::LINE_TAX_CATEGORY           => 'Une ligne n\'a pas de catégorie fiscale',
            ReadinessRequirement::LINE_ZERO_RATE              => 'Une ligne est à 0 % de TVA',
            ReadinessRequirement::SELLER_SIREN                => 'SIREN de la clinique manquant ou invalide',
            ReadinessRequirement::SELLER_VAT                  => 'N° de TVA de la clinique invalide',
            ReadinessRequirement::SELLER_ADDRESS              => 'Adresse de la clinique incomplète',
            ReadinessRequirement::SELLER_LEGAL_NAME           => 'Raison sociale de la clinique manquante',
            ReadinessRequirement::SELLER_NUMBERING_NOT_UNIQUE => 'Préfixe de numérotation déjà utilisé par un autre établissement du même SIREN',
        };
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function link(ReadinessRequirement $requirement, ?string $clientId, string $returnTo): array
    {
        // Where it is fixed: a card of the client sheet, or a field of the settings.
        [$route, $card] = match ($requirement) {
            ReadinessRequirement::BUYER_LEGAL_NAME,
            ReadinessRequirement::BUYER_SIREN   => ['clinic_clients_view', 'cFisc'],
            ReadinessRequirement::BUYER_ADDRESS => ['clinic_clients_view', 'cCoord'],
            ReadinessRequirement::LINE_TAX_CATEGORY,
            ReadinessRequirement::LINE_ZERO_RATE              => [null, null],
            ReadinessRequirement::SELLER_SIREN                => ['clinic_settings', 'id.siret'],
            ReadinessRequirement::SELLER_VAT                  => ['clinic_settings', 'id.tva'],
            ReadinessRequirement::SELLER_ADDRESS              => ['clinic_settings', 'id.adr'],
            ReadinessRequirement::SELLER_LEGAL_NAME           => ['clinic_settings', 'id.rs'],
            ReadinessRequirement::SELLER_NUMBERING_NOT_UNIQUE => ['clinic_settings_billing', 'fa.prefixe'],
        };

        if ('clinic_clients_view' === $route) {
            return null === $clientId
                ? [null, null]
                : [$this->urls->generate($route, ['id' => $clientId, 'card' => $card, '_returnTo' => $returnTo]), self::FIX_CLIENT];
        }

        if (null !== $route) {
            return [$this->urls->generate($route, ['card' => $card, '_returnTo' => $returnTo]), self::FIX_SETTINGS];
        }

        return [null, null];
    }
}
