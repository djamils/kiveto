<?php

declare(strict_types=1);

namespace App\Tests\Unit\Context\ClientPortal\Application\Query;

use App\Context\ClientPortal\Application\Port\ClinicLabel;
use App\Context\ClientPortal\Application\Port\PortalClientSnapshot;
use App\Context\ClientPortal\Application\Query\GetClinicLinkRequest\GetClinicLinkRequest;
use App\Context\ClientPortal\Application\Query\GetClinicLinkRequest\GetClinicLinkRequestHandler;
use App\Context\ClientPortal\Application\Query\ListClinicLinkRequests\ClinicLinkRequest;
use App\Context\ClientPortal\Application\Query\ListClinicLinkRequests\LinkRequestFile;
use App\Context\ClientPortal\Application\Query\ListClinicLinkRequests\ListClinicLinkRequests;
use App\Context\ClientPortal\Application\Query\ListClinicLinkRequests\ListClinicLinkRequestsHandler;
use App\Context\ClientPortal\Application\Service\ClinicLinkRequests;
use App\Context\ClientPortal\Application\Service\PersonName;
use App\Context\ClientPortal\Domain\Link\ClientLink;
use App\Context\ClientPortal\Domain\Link\ClientLinkId;
use App\Context\ClientPortal\Domain\Profile\OwnerProfile;
use App\Tests\Unit\Context\ClientPortal\Support\ClientPortalWorld as W;
use PHPUnit\Framework\TestCase;

/**
 * The « Rattachement » requests staff check (AC 76, 267): one per account,
 * the Portal profile name next to each file, the mismatch flag (case and
 * accents ignored) and the « déjà relié à un autre compte Portal » flag.
 */
final class ClinicLinkRequestsTest extends TestCase
{
    private const string USER  = '01960000-0000-7000-8000-0000000c0001';
    private const string USER2 = '01960000-0000-7000-8000-0000000c0002';
    private const string USER3 = '01960000-0000-7000-8000-0000000c0003';

    private W $world;

    protected function setUp(): void
    {
        $this->world                            = new W();
        $this->world->fakes->clinics[W::CLINIC] = new ClinicLabel('Clinique des Lilas', 'Lyon', 'Europe/Paris');
        $this->world->fakes->snapshots          = [
            W::CLINIC . '/' . W::CLIENT  => new PortalClientSnapshot('Sophie', 'LEFEVRE', null),
            W::CLINIC . '/' . W::CLIENT2 => new PortalClientSnapshot('Paul', 'Durand', null),
        ];
        $this->world->fakes->pets = [W::CLINIC . '/' . W::CLIENT => ['Moka', 'Rex']];
        $this->world->profiles->save(OwnerProfile::register(self::USER, 'Sophie', 'Lefèvre', 'v1', new \DateTimeImmutable('2026-10-29')));
    }

    public function testAClinicWithoutPendingLinkHasNoRequest(): void
    {
        self::assertSame([], new ListClinicLinkRequestsHandler($this->service())(new ListClinicLinkRequests(W::CLINIC)));
        self::assertNull(new GetClinicLinkRequestHandler($this->service())(new GetClinicLinkRequest(W::CLINIC, self::USER)));
    }

    public function testOneRequestPerAccountWithTheProfileNameNextToEachFile(): void
    {
        $this->pending('a2', self::USER, W::CLIENT2, '2026-11-02 09:30');
        $this->pending('a1', self::USER, W::CLIENT, '2026-11-02 09:00');
        $this->pending('a3', self::USER2, W::CLIENT, '2026-11-01 08:00');
        // Another account reaches the second file already.
        $this->world->links->save(ClientLink::confirm(ClientLinkId::fromString('01960000-0000-7000-8000-0000000000a4'), self::USER3, W::CLINIC, W::CLIENT2, new \DateTimeImmutable('2026-10-01')));
        // Another clinic's pending link is not this clinic's request.
        $this->world->links->save(ClientLink::requestFromSuggestion(ClientLinkId::fromString('01960000-0000-7000-8000-0000000000a5'), self::USER, W::CLINIC2, W::CLIENT, new \DateTimeImmutable('2026-11-02')));

        $requests = new ListClinicLinkRequestsHandler($this->service())(new ListClinicLinkRequests(W::CLINIC));

        self::assertSame([self::USER, self::USER2], array_map(static fn (ClinicLinkRequest $r): string => $r->userId, $requests));
        $sophie = $requests[0];
        self::assertSame(['Sophie Lefèvre', '2026-11-02 10:00 Europe/Paris', true, true], [
            $sophie->ownerName(), $sophie->requestedAt->format('Y-m-d H:i e'), $sophie->hasNameMismatch(), $sophie->hasFileLinkedElsewhere(),
        ]);
        self::assertSame([
            [W::CLIENT, 'Sophie LEFEVRE', ['Moka', 'Rex'], false, false],
            [W::CLIENT2, 'Paul Durand', [], true, true],
        ], array_map(static fn (LinkRequestFile $f): array => [$f->clientId, $f->name(), $f->petNames, $f->nameMismatch, $f->linkedToAnotherAccount], $sophie->files));

        // An account without Portal profile: no name, every file flagged.
        $anonymous = $requests[1];
        self::assertSame([null, null, true, false], [$anonymous->ownerName(), $anonymous->ownerFirstName, $anonymous->hasNameMismatch(), $anonymous->hasFileLinkedElsewhere()]);

        $one = new GetClinicLinkRequestHandler($this->service())(new GetClinicLinkRequest(W::CLINIC, self::USER));
        self::assertNotNull($one);
        self::assertSame([W::CLIENT, W::CLIENT2], array_map(static fn (LinkRequestFile $f): string => $f->clientId, $one->files));
        self::assertNull(new GetClinicLinkRequestHandler($this->service())(new GetClinicLinkRequest(W::CLINIC, self::USER3)), 'confirmed only');
    }

    public function testAFileNoLongerKnownIsShownWithoutName(): void
    {
        $this->world->fakes->snapshots = [];
        $this->pending('a1', self::USER, W::CLIENT, '2026-11-02 09:00');

        $file = new GetClinicLinkRequestHandler($this->service())(new GetClinicLinkRequest(W::CLINIC, self::USER))?->files[0];

        self::assertNotNull($file);
        self::assertSame([null, null, true], [$file->name(), $file->firstName, $file->nameMismatch]);
    }

    public function testLastNamesAreComparedWithoutCaseAccentsNorExtraSpaces(): void
    {
        self::assertTrue(PersonName::sameLastName('Lefèvre', 'LEFEVRE'));
        self::assertTrue(PersonName::sameLastName(' Le  Gall ', 'le gall'));
        self::assertTrue(PersonName::sameLastName('Åström', 'astrom'));
        self::assertFalse(PersonName::sameLastName('Martin', 'Martins'));
        self::assertFalse(PersonName::sameLastName('Durand', 'Dupont'));
    }

    private function pending(string $suffix, string $userId, string $clientId, string $requestedAtUtc): void
    {
        $this->world->links->save(ClientLink::requestFromSuggestion(
            ClientLinkId::fromString('01960000-0000-7000-8000-0000000000' . $suffix),
            $userId,
            W::CLINIC,
            $clientId,
            new \DateTimeImmutable($requestedAtUtc, new \DateTimeZone('UTC')),
        ));
    }

    private function service(): ClinicLinkRequests
    {
        return new ClinicLinkRequests($this->world->links, $this->world->profiles, $this->world->fakes, $this->world->fakes, $this->world->fakes);
    }
}
