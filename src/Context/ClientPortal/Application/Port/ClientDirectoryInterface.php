<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/** What ClientPortal reads of a clinic client (Client context). */
interface ClientDirectoryInterface
{
    /** How to reach the client; null when the client does not belong to the clinic. */
    public function contactOf(string $clinicId, string $clientId): ?PortalClientContact;

    /**
     * How to reach several clients, in one read, by client id; a client who
     * does not belong to the clinic is left out.
     *
     * @param list<string> $clientIds
     *
     * @return array<string, PortalClientContact>
     */
    public function contactsOf(string $clinicId, array $clientIds): array;

    /** Identity and postal address of the client; null when unknown to the clinic. */
    public function snapshotOf(string $clinicId, string $clientId): ?PortalClientSnapshot;

    /**
     * The same for several clients in one read, by client id; a client
     * unknown to the clinic is left out.
     *
     * @param list<string> $clientIds
     *
     * @return array<string, PortalClientSnapshot>
     */
    public function snapshotsOf(string $clinicId, array $clientIds): array;

    /**
     * Across clinics: the non-archived files of active clinics whose
     * `mobile` phone is exactly this number, by clinic then oldest first.
     *
     * @return list<MobileFileMatch>
     */
    public function mobileMatches(string $phone): array;
}
