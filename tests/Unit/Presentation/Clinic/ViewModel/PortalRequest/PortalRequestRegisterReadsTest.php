<?php

declare(strict_types=1);

namespace App\Tests\Unit\Presentation\Clinic\ViewModel\PortalRequest;

use App\Context\ClientPortal\Application\Query\ListClinicBookingRequests\ListClinicBookingRequests;
use App\Context\ClientPortal\Application\Query\ListClinicLinkRequests\ListClinicLinkRequests;
use App\Context\ClientPortal\Application\Query\ListClinicPortalRequests\ClinicPortalRequest;
use App\Context\ClientPortal\Application\Query\ListClinicPortalRequests\ListClinicPortalRequests;
use App\Context\Prescription\Application\Query\GetPrescriptionDetails\GetPrescriptionDetails;
use App\Context\Prescription\Application\Query\ListPrescriptionPortalSummaries\ListPrescriptionPortalSummaries;
use App\Context\Prescription\Application\Query\ListPrescriptionPortalSummaries\PrescriptionPortalSummary;
use App\Presentation\Clinic\ViewModel\PortalRequest\PortalRequestPageBuilder;
use App\Presentation\Clinic\ViewModel\PortalRequest\PortalRequestQueue;
use App\Presentation\Clinic\ViewModel\PortalRequest\PortalRequestRegisterFilters;
use App\Shared\Application\Bus\QueryBusInterface;
use App\Shared\Application\Pagination\CursorPage;
use App\Tests\Shared\Time\FrozenClock;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * REG-01, REG-03: what the « Demandes » register reads. The whole open
 * queue whatever the history; the 2000 cap on the answered requests only;
 * « À traiter » (every keystroke of the search) reads no history and the
 * medicines of the renewals in one go.
 */
final class PortalRequestRegisterReadsTest extends TestCase
{
    private const string CLINIC = '01960000-0000-7000-8000-00000000c001';

    /** @var list<ClinicPortalRequest> newest first */
    private array $requests = [];

    /** @var list<object> */
    private array $asked = [];

    public function testTheOldestWaitingRequestIsListedBehindALongHistory(): void
    {
        foreach (range(1, 2100) as $n) {
            $this->requests[] = $this->request($n, 'done', '2026-10-30 08:00:00');
        }

        $this->requests[] = $this->request(9001, 'submitted', '2026-09-01 08:00:00');

        $waiting = $this->builder()->register(self::CLINIC, new PortalRequestRegisterFilters());
        self::assertSame([1, $this->id(9001)], [$waiting['total'], $waiting['rows'][0]['id']]);

        $all = $this->builder()->register(self::CLINIC, new PortalRequestRegisterFilters(statut: PortalRequestRegisterFilters::ALL));
        self::assertSame(['submitted' => 1, 'done' => PortalRequestQueue::MAX_HISTORY, 'rejected' => 0, 'tout' => 1 + PortalRequestQueue::MAX_HISTORY], $all['counts']);
    }

    public function testTheWholeOpenQueueIsListed(): void
    {
        foreach (range(1, 620) as $n) {
            $this->requests[] = $this->request($n, 'submitted', '2026-10-30 08:00:00');
        }

        $register = $this->builder()->register(self::CLINIC, new PortalRequestRegisterFilters());

        self::assertSame([620, 620], [$register['total'], $register['head']['pending']]);
    }

    public function testASearchOfTheWaitingRequestsReadsNoHistoryAndTheMedicinesInOneGo(): void
    {
        $this->requests[] = $this->request(1, 'submitted', '2026-10-30 09:00:00', renewal: true);
        $this->requests[] = $this->request(2, 'submitted', '2026-10-30 08:00:00', renewal: true);
        $this->requests[] = $this->request(3, 'done', '2026-10-29 08:00:00', renewal: true);

        $register = $this->builder()->register(self::CLINIC, new PortalRequestRegisterFilters(q: 'meloxidyl'));

        self::assertSame([2, ['submitted' => 2, 'done' => null, 'rejected' => null, 'tout' => null]], [$register['total'], $register['counts']]);
        self::assertSame('Meloxidyl', $register['rows'][0]['subject']);

        $portal = array_values(array_filter($this->asked, static fn (object $q): bool => $q instanceof ListClinicPortalRequests));
        self::assertSame(['submitted'], array_values(array_unique(array_map(static fn (ListClinicPortalRequests $q): ?string => $q->status, $portal))), 'no history read');
        $decided = [];

        foreach ($this->asked as $query) {
            if ($query instanceof ListClinicLinkRequests || $query instanceof ListClinicBookingRequests) {
                $decided[] = $query->decided;
            }
        }

        self::assertSame([false, false], $decided, 'no answered « Rattachement » nor « Nouveau client » read');
        self::assertCount(1, array_filter($this->asked, static fn (object $q): bool => $q instanceof ListPrescriptionPortalSummaries));
        self::assertCount(0, array_filter($this->asked, static fn (object $q): bool => $q instanceof GetPrescriptionDetails));
    }

    public function testAnEmptyQueueStillTellsWhetherAnythingWasEverReceived(): void
    {
        $this->requests[] = $this->request(1, 'rejected', '2026-10-29 08:00:00');

        $register = $this->builder()->register(self::CLINIC, new PortalRequestRegisterFilters());

        self::assertSame([0, false, 1], [$register['total'], $register['head']['blank'], $register['counts']['rejected']]);
    }

    /** The fake query bus: requests paged by offset, no link nor booking, the medicines. */
    public function answer(object $query): mixed
    {
        $this->asked[] = $query;

        if ($query instanceof ListClinicPortalRequests) {
            $matching = array_values(array_filter($this->requests, static fn (ClinicPortalRequest $r): bool => null === $query->status ? 'closed' !== $r->status : $r->status === $query->status));
            $offset   = (int) ($query->cursor ?? '0');
            $limit    = $query->limit ?? 50;

            return new CursorPage(\array_slice($matching, $offset, $limit), $offset + $limit < \count($matching) ? (string) ($offset + $limit) : null);
        }

        if ($query instanceof ListPrescriptionPortalSummaries) {
            return array_map(static fn (string $id): PrescriptionPortalSummary => new PrescriptionPortalSummary($id, 'ISSUED', null, true, 'Meloxidyl'), $query->prescriptionIds);
        }

        if ($query instanceof GetPrescriptionDetails) {
            throw new \LogicException('One prescription read per row.');
        }

        return [];
    }

    private function builder(): PortalRequestPageBuilder
    {
        $bus = new class($this) implements QueryBusInterface {
            public function __construct(private readonly PortalRequestRegisterReadsTest $test)
            {
            }

            public function ask(object $query, object ...$stamps): mixed
            {
                return $this->test->answer($query);
            }
        };

        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturn('/portal-requests/x');

        return new PortalRequestPageBuilder($bus, $urls, new PortalRequestQueue($bus), new FrozenClock(new \DateTimeImmutable('2026-10-30 10:00:00', new \DateTimeZone('UTC'))), $this->createStub(CsrfTokenManagerInterface::class));
    }

    private function request(int $n, string $status, string $at, bool $renewal = false): ClinicPortalRequest
    {
        return new ClinicPortalRequest(
            $this->id($n),
            $renewal ? 'prescription_renewal' : 'pet_change',
            '01960000-0000-7000-8000-00000000d001',
            'Sophie Dupont',
            '01960000-0000-7000-8000-00000000a001',
            'Rex',
            $renewal ? null : 'name',
            $renewal ? null : 'Rex II',
            $renewal ? \sprintf('01960000-0000-7000-8000-%012d', 500000 + $n) : null,
            $renewal ? 'improved' : null,
            null,
            $status,
            new \DateTimeImmutable($at, new \DateTimeZone('UTC')),
            'submitted' === $status ? null : new \DateTimeImmutable('2026-10-30 09:00:00', new \DateTimeZone('UTC')),
            'submitted' === $status ? null : 'Dr Lambert',
            null,
            null,
            false,
        );
    }

    private function id(int $n): string
    {
        return \sprintf('01960000-0000-7000-8000-%012d', $n);
    }
}
