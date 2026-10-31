<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

final readonly class PrescriptionPortalState
{
    /**
     * @param bool $issued    false once cancelled (no longer listable)
     * @param bool $renewable issued and without controlled substance, expired or not
     */
    public function __construct(
        public bool $issued,
        public ?\DateTimeImmutable $validUntil,
        public bool $renewable,
    ) {
    }
}
