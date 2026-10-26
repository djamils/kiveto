<?php

declare(strict_types=1);

namespace App\Context\Billing\Domain\ValueObject;

/**
 * When an invoice falls due (BT-9) and how its terms read (BT-20), with the
 * legal mentions a professional invoice must carry (BT-22 notes).
 */
final readonly class PaymentTerms
{
    /** BT-22 subject code => text: late-payment penalties, recovery fee, no early-payment discount. */
    public const array PROFESSIONAL_MENTIONS = [
        'PMD' => 'En cas de retard de paiement, une pénalité égale à 3 fois le taux d\'intérêt légal sera exigible.',
        'PMT' => 'Indemnité forfaitaire pour frais de recouvrement en cas de retard de paiement : 40 €.',
        'AAB' => 'Aucun escompte pour paiement anticipé.',
    ];

    private function __construct(
        public ?\DateTimeImmutable $dueDate,
        public string $note,
    ) {
    }

    /**
     * An invoice settled when issued has no due date.
     */
    public static function settled(): self
    {
        return new self(null, 'Facture acquittée');
    }

    public static function dueIn(int $days, \DateTimeImmutable $issuedAt): self
    {
        if ($days < 0) {
            throw new \InvalidArgumentException('Payment terms cannot be negative.');
        }

        return new self(
            $issuedAt->setTime(0, 0)->modify(\sprintf('+%d days', $days)),
            \sprintf('Paiement à %d jours', $days),
        );
    }

    public static function reconstitute(?\DateTimeImmutable $dueDate, string $note): self
    {
        return new self($dueDate, $note);
    }
}
