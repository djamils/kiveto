<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Api\Portal;

use App\Context\ClientPortal\Application\Command\ResolvePortalRequest\ResolvePortalRequest;

/**
 * Slice 6: the owner's follow-up of their change and renewal requests
 * (AC 234). Today is Monday 2 November 2026, 11:00 in Paris.
 */
final class PortalOwnerRequestsApiTest extends RequestsApiTestCase
{
    private const string OTHER_PHONE = '+33615000605';

    private string $rex;

    protected function setUp(): void
    {
        $this->clientIp = '203.0.113.164';
        parent::setUp();

        $this->rex = $this->pet('Rex');
    }

    public function testWithoutRequestTheListIsEmpty(): void
    {
        self::assertSame(['items' => [], 'nextCursor' => null], self::json($this->authGet(self::clinicPath('/requests')), 200));
    }

    public function testTheCallersRequestsAreListedNewestFirstWithTheirResolution(): void
    {
        $vet          = $this->veterinarian('Claire', 'Rousseau');
        $prescription = $this->prescription($this->rex, prescriberId: $vet);
        $change       = $this->submitChange('key-change');

        $this->travelTo('2026-11-02 10:30:00');
        $renewal = self::string(self::json($this->authPost(self::clinicPath('/prescriptions/' . $prescription . '/renewal-requests'), ['petCondition' => 'improved'], 'key-renewal'), 202)['requestId']);

        $this->travelTo('2026-11-02 11:00:00');
        $resulting = $this->prescription($this->rex, issuedAtUtc: '2026-11-02 11:00:00', prescriberId: $vet);
        $this->bus()->dispatch(new ResolvePortalRequest(self::CLINIC, $renewal, 'done', $vet, 'Dr Claire Rousseau', 'Ordonnance prête à l\'accueil.', $resulting));

        // Another owner of the clinic and their request stay invisible
        $other = $this->otherOwner(self::OTHER_PHONE);
        self::json($this->authPost(self::clinicPath('/pets/' . $this->pet('Nala', self::OTHER_CLIENT) . '/change-requests'), ['field' => 'name', 'proposedValue' => 'Nala II'], 'key-other', $other), 202);

        $items = self::items(self::json($this->authGet(self::clinicPath('/requests')), 200));

        self::assertSame([
            [
                'id'                      => $renewal,
                'kind'                    => 'prescription_renewal',
                'petId'                   => $this->rex,
                'field'                   => null,
                'proposedValue'           => null,
                'prescriptionId'          => $prescription,
                'status'                  => 'done',
                'submittedAt'             => '2026-11-02T11:30:00+01:00',
                'resolvedAt'              => '2026-11-02T12:00:00+01:00',
                'resolutionMessage'       => 'Ordonnance prête à l\'accueil.',
                'resultingPrescriptionId' => $resulting,
            ],
            [
                'id'                      => $change,
                'kind'                    => 'pet_change',
                'petId'                   => $this->rex,
                'field'                   => 'weight',
                'proposedValue'           => '12,4',
                'prescriptionId'          => null,
                'status'                  => 'submitted',
                'submittedAt'             => '2026-11-02T11:00:00+01:00',
                'resolvedAt'              => null,
                'resolutionMessage'       => null,
                'resultingPrescriptionId' => null,
            ],
        ], $items);
    }

    public function testTheListIsPaginatedByCursor(): void
    {
        $older = $this->submitChange('key-older');
        $this->travelTo('2026-11-02 10:05:00');
        $newer = $this->submitChange('key-newer', 'sterilization', 'neutered');

        $first = self::json($this->authGet(self::clinicPath('/requests?limit=1')), 200);
        self::assertSame([$newer], array_column(self::items($first), 'id'));

        $second = self::json($this->authGet(self::clinicPath('/requests?limit=1&cursor=' . self::string($first['nextCursor']))), 200);
        self::assertSame([$older], array_column(self::items($second), 'id'));
        self::assertNull($second['nextCursor']);
    }

    public function testABadCursorOrAnUnlinkedClinicIsRefusedAndNothingCancelsARequest(): void
    {
        $change = $this->submitChange('key-cancel');

        self::assertSame('cursor_invalid', self::problemType($this->authGet(self::clinicPath('/requests?cursor=%%%')), 400));
        self::assertSame('not_found', self::problemType($this->authGet(self::clinicPath('/requests', self::OTHER_CLINIC)), 404));

        $this->browser->request('DELETE', self::BASE . self::clinicPath('/requests/' . $change), server: ['HTTP_ACCEPT' => 'application/json', 'REMOTE_ADDR' => $this->clientIp, 'HTTP_AUTHORIZATION' => 'Bearer ' . $this->token]);
        self::assertContains($this->browser->getResponse()->getStatusCode(), [404, 405]);
        self::assertSame('submitted', self::items(self::json($this->authGet(self::clinicPath('/requests')), 200))[0]['status']);
    }

    protected function phone(): string
    {
        return '+33615000604';
    }

    private function submitChange(string $key, string $field = 'weight', string $value = '12,4'): string
    {
        return self::string(self::json($this->authPost(self::clinicPath('/pets/' . $this->rex . '/change-requests'), ['field' => $field, 'proposedValue' => $value], $key), 202)['requestId']);
    }
}
