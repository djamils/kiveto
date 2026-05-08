<?php

declare(strict_types=1);

namespace App\Context\Regulatory\Domain\Policy;

interface RegulatoryPolicyInterface
{
    public function getAuthorityNotificationDeadline(\DateTimeImmutable $admissionOpenedAt): \DateTimeImmutable;

    public function getStrayCustodyDeadline(\DateTimeImmutable $admissionOpenedAt): \DateTimeImmutable;
}
