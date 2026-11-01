<?php

declare(strict_types=1);

namespace App\Tests\Integration\Presentation\Clinic\PortalRequest;

use App\Presentation\Clinic\Security\ClinicPermissionGuard;

/**
 * « Traiter » / « Refuser » a Portal request from VetApp (AC 69, 231, 240):
 * the answer is stored with its author, message and linked prescription; a
 * second answer gets the flash « Demande déjà traitée par … »; the
 * permissions and the CSRF token are checked.
 */
final class ResolvePortalRequestControllerTest extends PortalRequestTestCase
{
    /** AC 69. */
    public function testARenewalIsProcessedWithAMessageAndThePrescriptionWrittenForIt(): void
    {
        $id     = $this->renewal();
        $result = $this->prescription($this->rex, '2026-11-02 10:30:00');
        $this->signIn();

        $this->resolve($id, ['outcome' => 'done', 'message' => 'Prête à l’accueil.', 'resulting_prescription_id' => $result]);

        self::assertResponseRedirects('/portal-requests/' . $id, 303);
        self::assertStringContainsString('Demande traitée — le client est prévenu.', $this->flashes());
        $row = $this->connection()->fetchAssociative(
            'SELECT status, resolved_by_name, resolution_message, BIN_TO_UUID(resulting_prescription_id) AS result FROM client_portal__prescription_renewal_requests WHERE id = UUID_TO_BIN(?)',
            [$id],
        );
        self::assertIsArray($row);
        self::assertSame('done', $row['status']);
        self::assertSame('Prête à l’accueil.', $row['resolution_message']);
        self::assertSame($result, $row['result']);
        self::assertNotEmpty($row['resolved_by_name']);

        // The detail now shows the answer, without the form.
        $html = (string) $this->browser->getResponse()->getContent();
        self::assertStringContainsString('Prête à l’accueil.', $html);
        self::assertStringNotContainsString('id="prqForm"', $html);
    }

    public function testAPrescriptionIssuedBeforeTheRequestIsRefusedWithItsReason(): void
    {
        $id     = $this->renewal();
        $before = $this->prescription($this->rex, '2026-10-20 09:00:00');
        $this->signIn();

        $this->resolve($id, ['outcome' => 'done', 'resulting_prescription_id' => $before]);

        self::assertStringContainsString('Choisissez une ordonnance émise pour cet animal après la demande.', $this->flashes());
        self::assertSame('submitted', $this->statusOf($id, 'client_portal__prescription_renewal_requests'));
    }

    /** AC 231: the second answer loses, with the name of whoever answered first. */
    public function testASecondAnswerGetsTheAlreadyHandledFlash(): void
    {
        $id = $this->change('name', 'Rex II');
        $this->signIn();
        $token = $this->token($id);

        $this->resolve($id, ['outcome' => 'done'], $token);
        self::assertStringContainsString('Demande traitée', $this->flashes());

        $this->resolve($id, ['outcome' => 'rejected', 'message' => 'Non.'], $token);
        self::assertResponseRedirects('/portal-requests/' . $id, 303);
        self::assertMatchesRegularExpression('/Demande déjà traitée par .+\./u', $this->flashes());
        self::assertSame('done', $this->statusOf($id, 'client_portal__pet_change_requests'));
    }

    public function testAChangeRequestIsRejected(): void
    {
        $id = $this->change('chip_number', '250269801234567');
        $this->signIn();

        $this->resolve($id, ['outcome' => 'rejected', 'message' => 'Puce déjà enregistrée.']);

        self::assertStringContainsString('Demande refusée — le client est prévenu.', $this->flashes());
        self::assertSame('rejected', $this->statusOf($id, 'client_portal__pet_change_requests'));
    }

    public function testAnUnknownOutcomeOrAForgedTokenIsRefused(): void
    {
        $id = $this->change('name', 'Rex II');
        $this->signIn();

        $this->resolve($id, ['outcome' => 'closed']);
        self::assertStringContainsString('Choisissez « Traiter » ou « Refuser ».', $this->flashes());

        $this->resolve($id, ['outcome' => 'done'], 'forged');
        self::assertResponseStatusCodeSame(403);
        self::assertSame('submitted', $this->statusOf($id, 'client_portal__pet_change_requests'));
    }

    /** AC 240: a renewal needs `create_prescription`. */
    public function testWithoutCreatePrescriptionARenewalCannotBeAnswered(): void
    {
        $renewal = $this->renewal();
        $change  = $this->change('name', 'Rex II');
        $this->withoutPermissions(ClinicPermissionGuard::CREATE_PRESCRIPTION);
        $this->signIn();

        $crawler = $this->browser->request('GET', self::HOST . '/portal-requests/' . $renewal);
        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('#prqForm'));
        $token = $this->token($change);

        $this->resolve($renewal, ['outcome' => 'done'], $token);
        self::assertStringContainsString('Seul un membre habilité à prescrire', $this->flashes());
        self::assertSame('submitted', $this->statusOf($renewal, 'client_portal__prescription_renewal_requests'));

        // A change request stays open to them.
        $this->resolve($change, ['outcome' => 'done'], $token);
        self::assertSame('done', $this->statusOf($change, 'client_portal__pet_change_requests'));
    }

    /** AC 240: without `manage_animals`, no answer at all; another clinic's request is not found. */
    public function testWithoutManageAnimalsOrInAnotherClinicNothingIsAnswered(): void
    {
        $id = $this->change('name', 'Rex II');
        $this->signIn();
        $token = $this->token($id);

        $this->connection()->executeStatement('UPDATE client_portal__pet_change_requests SET clinic_id = UUID_TO_BIN(?) WHERE id = UUID_TO_BIN(?)', [self::OTHER, $id]);
        $this->resolve($id, ['outcome' => 'done'], $token);
        self::assertResponseStatusCodeSame(404);
        $this->connection()->executeStatement('UPDATE client_portal__pet_change_requests SET clinic_id = UUID_TO_BIN(?) WHERE id = UUID_TO_BIN(?)', [self::CLINIC, $id]);

        $this->withoutPermissions(ClinicPermissionGuard::MANAGE_ANIMALS);
        $this->resolve($id, ['outcome' => 'done'], $token);
        self::assertResponseStatusCodeSame(403);
        self::assertSame('submitted', $this->statusOf($id, 'client_portal__pet_change_requests'));
    }

    private function statusOf(string $id, string $table): string
    {
        $status = $this->connection()->fetchOne('SELECT status FROM ' . $table . ' WHERE id = UUID_TO_BIN(?)', [$id]);
        \assert(\is_string($status));

        return $status;
    }
}
