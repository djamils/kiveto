<?php

declare(strict_types=1);

namespace App\System\Notification\Application\Command\UpsertClinicMessagingProfile;

use App\Shared\Application\Bus\CommandInterface;

/**
 * Clinic pushes what Notification needs to write on behalf of one of its
 * clinics (Clinic is the source of truth; this is a read-only copy).
 *
 * @see \App\System\Notification\Domain\Clinic\ClinicMessagingProfile
 */
final readonly class UpsertClinicMessagingProfile implements CommandInterface
{
    /**
     * @param string  $emailLocalPart the part before « @mail.kiveto.fr » (the clinic slug)
     * @param ?string $replyTo        the letterhead e-mail
     * @param string  $smsSender      at most 11 letters, digits or spaces; empty → « Kiveto »
     * @param string  $addressLine    « 18 rue Lepic, 75018 Paris »
     * @param string  $city           shown under the clinic name (« Paris 18e »)
     * @param string  $phoneDisplay   « 01 42 64 18 00 »
     * @param ?string $brandColor     #RRGGBB, colour of the e-mail buttons
     * @param ?string $logoStorageKey FileStorage key of the clinic logo
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
        public string $phoneDisplay,
        public ?string $brandColor,
        public ?string $logoStorageKey,
    ) {
    }
}
