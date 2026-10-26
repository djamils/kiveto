<?php

declare(strict_types=1);

namespace App\Tests\Unit\System\Notification\Support;

use App\System\Notification\Domain\Clinic\ClinicMessagingProfile;
use App\System\Notification\Domain\Clinic\ClinicMessagingProfileRepositoryInterface;

final class InMemoryProfiles implements ClinicMessagingProfileRepositoryInterface
{
    /** @var array<string, ClinicMessagingProfile> */
    public array $profiles = [];

    /** @var list<string> clinic ids read with a row lock */
    public array $locked = [];

    public function save(ClinicMessagingProfile $profile): void
    {
        $this->profiles[$profile->clinicId] = $profile;
    }

    public function find(string $clinicId): ?ClinicMessagingProfile
    {
        return $this->profiles[$clinicId] ?? null;
    }

    public function findForUpdate(string $clinicId): ?ClinicMessagingProfile
    {
        $this->locked[] = $clinicId;

        return $this->profiles[$clinicId] ?? null;
    }

    public static function montmartre(string $clinicId = Messages::CLINIC, int $cap = 500, ?string $logo = null): ClinicMessagingProfile
    {
        return ClinicMessagingProfile::of(
            clinicId: $clinicId,
            name: 'Clinique Montmartre',
            emailLocalPart: 'clinique-montmartre',
            replyTo: 'accueil@clinique-montmartre.fr',
            smsSender: 'Montmartre',
            overageCapSegments: $cap,
            addressLine: '18 rue Lepic, 75018 Paris',
            city: 'Paris 18e',
            phoneDisplay: '01 42 64 18 00',
            brandColor: '#4338CA',
            logoStorageKey: $logo,
        );
    }
}
