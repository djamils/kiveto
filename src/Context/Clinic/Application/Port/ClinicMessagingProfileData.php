<?php

declare(strict_types=1);

namespace App\Context\Clinic\Application\Port;

/**
 * What Notification needs to write on behalf of a clinic.
 */
final readonly class ClinicMessagingProfileData
{
    /**
     * @param string  $emailLocalPart the clinic slug (« clinique-montmartre »@mail.kiveto.app)
     * @param ?string $replyTo        the letterhead e-mail
     * @param string  $addressLine    « 18 rue Lepic, 75018 Paris », empty when unknown
     * @param ?string $phone          the letterhead phone, E.164
     */
    public function __construct(
        public string $clinicId,
        public string $name,
        public string $emailLocalPart,
        public ?string $replyTo,
        public string $smsSender,
        public int $overageCapSegments,
        public string $addressLine,
        public string $city,
        public ?string $phone,
        public ?string $brandColor,
        public ?string $logoStorageKey,
    ) {
    }
}
