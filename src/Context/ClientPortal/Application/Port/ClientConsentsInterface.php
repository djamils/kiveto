<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Port;

/** The communication consents of the clinic's client files (Client context). */
interface ClientConsentsInterface
{
    /**
     * The consents of the client files, for a data export: per kind, the
     * union of the channels granted on the files; and every change of
     * their history, latest first. Instants are UTC.
     *
     * @param list<string> $clientIds
     *
     * @return array{
     *     consents: list<array{kind: string, channels: list<string>}>,
     *     history: list<array{kind: string, channel: string, action: string, source: string, at: \DateTimeImmutable}>
     * }
     */
    public function exportOf(string $clinicId, array $clientIds): array;

    /**
     * The consents of the client files, keyed by client id; a file unknown
     * to the clinic is absent.
     *
     * @param list<string> $clientIds
     *
     * @return array<string, ClientConsentRecord>
     */
    public function consentsOf(string $clinicId, array $clientIds): array;

    /**
     * One batch of the consent history of the client files, newest first on
     * `(at, id)`, strictly after the given position when there is one.
     *
     * @param list<string>                                   $clientIds
     * @param array{at: \DateTimeImmutable, id: string}|null $after
     *
     * @return list<ClientConsentChange>
     */
    public function historyOf(string $clinicId, array $clientIds, ?array $after, int $limit): array;
}
