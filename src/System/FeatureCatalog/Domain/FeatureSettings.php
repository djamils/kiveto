<?php

declare(strict_types=1);

namespace App\System\FeatureCatalog\Domain;

/**
 * The global settings of a feature (F2 « Réglages globaux »): its status,
 * whether a new clinic starts with it, which clinics it is kept for, and
 * whether it is cut off everywhere. A feature never saved reads its
 * definition's initial settings.
 */
final class FeatureSettings
{
    private function __construct(
        private readonly string $code,
        private FeatureStatus $status,
        private bool $defaultOn,
        private ?\DateTimeImmutable $updatedAt,
        private FeatureAudience $audience,
        private ?FeatureCutOff $cutOff,
    ) {
    }

    public static function initial(FeatureDefinition $definition): self
    {
        return new self($definition->code, $definition->initialStatus, $definition->initialDefaultOn, null, FeatureAudience::ALL, null);
    }

    public static function reconstitute(
        string $code,
        FeatureStatus $status,
        bool $defaultOn,
        ?\DateTimeImmutable $updatedAt,
        FeatureAudience $audience = FeatureAudience::ALL,
        ?FeatureCutOff $cutOff = null,
    ): self {
        return new self($code, $status, $defaultOn, $updatedAt, $audience, $cutOff);
    }

    /**
     * @param FeatureAudience|null $audience null: unchanged
     *
     * @throws \DomainException an upcoming feature, or a status set to « À venir »
     *
     * @return list<string> what changed, in French, for the history
     */
    public function change(FeatureStatus $status, bool $defaultOn, \DateTimeImmutable $at, ?FeatureAudience $audience = null): array
    {
        if ($this->isUpcoming() || FeatureStatus::UPCOMING === $status) {
            throw new \DomainException('Une fonctionnalité « À venir » ne se règle pas : son module n’existe pas encore.');
        }

        $audience ??= $this->audience;
        $changes = [];

        if ($status !== $this->status) {
            $changes[] = \sprintf('Statut : %s → %s', $this->status->label(), $status->label());
        }

        if ($defaultOn !== $this->defaultOn) {
            $changes[] = $defaultOn ? 'Activée par défaut pour les nouvelles cliniques' : 'Plus activée par défaut pour les nouvelles cliniques';
        }

        if ($audience !== $this->audience) {
            $changes[] = \sprintf('Réservée à : %s → %s', $this->audience->label(), $audience->label());
        }

        if ([] !== $changes) {
            $this->status    = $status;
            $this->defaultOn = $defaultOn;
            $this->audience  = $audience;
            $this->updatedAt = $at;
        }

        return $changes;
    }

    /**
     * The kill switch: no clinic has the feature until it is restored.
     *
     * @throws \DomainException an upcoming feature, one already cut off, or no reason
     */
    public function cut(string $reason, ?string $byName, \DateTimeImmutable $at): void
    {
        if ($this->isUpcoming()) {
            throw new \DomainException('Une fonctionnalité « À venir » est déjà coupée partout : son module n’existe pas encore.');
        }

        if (null !== $this->cutOff) {
            throw new \DomainException('Cette fonctionnalité est déjà coupée partout.');
        }

        $reason = trim($reason);

        if ('' === $reason) {
            throw new \DomainException('Indique pourquoi tu la coupes : le motif va au journal d’audit.');
        }

        $this->cutOff    = new FeatureCutOff(mb_substr($reason, 0, 255), $at, $byName);
        $this->updatedAt = $at;
    }

    /**
     * The clinics have it again as they had it before the cut.
     *
     * @throws \DomainException a feature that is not cut off
     */
    public function restore(\DateTimeImmutable $at): void
    {
        if (null === $this->cutOff) {
            throw new \DomainException('Cette fonctionnalité n’est pas coupée.');
        }

        $this->cutOff    = null;
        $this->updatedAt = $at;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function status(): FeatureStatus
    {
        return $this->status;
    }

    public function isDefaultOn(): bool
    {
        return $this->defaultOn;
    }

    public function updatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function audience(): FeatureAudience
    {
        return $this->audience;
    }

    public function cutOff(): ?FeatureCutOff
    {
        return $this->cutOff;
    }

    public function isUpcoming(): bool
    {
        return FeatureStatus::UPCOMING === $this->status;
    }
}
