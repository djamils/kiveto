<?php

declare(strict_types=1);

namespace App\System\FeatureCatalog\Domain;

/**
 * The global settings of a feature (F2 « Réglages globaux »): its status and
 * whether a new clinic starts with it. A feature never saved reads its
 * definition's initial settings.
 */
final class FeatureSettings
{
    private function __construct(
        private readonly string $code,
        private FeatureStatus $status,
        private bool $defaultOn,
        private ?\DateTimeImmutable $updatedAt,
    ) {
    }

    public static function initial(FeatureDefinition $definition): self
    {
        return new self($definition->code, $definition->initialStatus, $definition->initialDefaultOn, null);
    }

    public static function reconstitute(string $code, FeatureStatus $status, bool $defaultOn, ?\DateTimeImmutable $updatedAt): self
    {
        return new self($code, $status, $defaultOn, $updatedAt);
    }

    /**
     * @throws \DomainException an upcoming feature, or a status set to « À venir »
     *
     * @return list<string> what changed, in French, for the history
     */
    public function change(FeatureStatus $status, bool $defaultOn, \DateTimeImmutable $at): array
    {
        if (FeatureStatus::UPCOMING === $this->status || FeatureStatus::UPCOMING === $status) {
            throw new \DomainException('Une fonctionnalité « À venir » ne se règle pas : son module n’existe pas encore.');
        }

        $changes = [];

        if ($status !== $this->status) {
            $changes[] = \sprintf('Statut : %s → %s', $this->status->label(), $status->label());
        }

        if ($defaultOn !== $this->defaultOn) {
            $changes[] = $defaultOn ? 'Activée par défaut pour les nouvelles cliniques' : 'Plus activée par défaut pour les nouvelles cliniques';
        }

        if ([] !== $changes) {
            $this->status    = $status;
            $this->defaultOn = $defaultOn;
            $this->updatedAt = $at;
        }

        return $changes;
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

    public function isUpcoming(): bool
    {
        return FeatureStatus::UPCOMING === $this->status;
    }
}
