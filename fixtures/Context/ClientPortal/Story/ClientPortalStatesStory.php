<?php

declare(strict_types=1);

namespace App\Fixtures\Context\ClientPortal\Story;

use App\Context\Animal\Application\Command\CreateAnimal\CreateAnimal;
use App\Context\Client\Domain\ValueObject\ContactLabel;
use App\Fixtures\Context\Client\Factory\ClientEntityFactory;
use App\Fixtures\Context\Client\Factory\ContactMethodEntityFactory;
use App\Fixtures\Context\ClientPortal\Factory\ClientLinkEntityFactory;
use App\Fixtures\Context\ClientPortal\Factory\OwnerProfileEntityFactory;
use App\Fixtures\Context\ClientPortal\Factory\PortalInvitationEntityFactory;
use App\Fixtures\Context\Clinic\Story\ClinicDataStory;
use App\Fixtures\System\IdentityAccess\Factory\PortalUserFactory;
use App\Fixtures\System\IdentityAccess\Story\PortalUserStory;
use App\Shared\Application\Bus\CommandBusInterface;
use App\Shared\Domain\Time\ClockInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Story;

/**
 * Every Portal state of the client sheet (« Inviter au Portal », D16), in the
 * Paris clinic (ClinicDataStory::INDEPENDENT_CLINIC_ID):
 *
 * | Client                      | Mobile           | Sheet                                                    |
 * |-----------------------------|------------------|----------------------------------------------------------|
 * | Mme Camille Fontaine        | +33611223344     | « Portal : non invité » · « Inviter au Portal »          |
 * | M. Antoine Roussel          | +33622334455     | « Invité le … » (yesterday, SMS) · « Renvoyer l'invitation » |
 * | Mme Laura Perrin            | +33633445566     | « Invitation expirée » (10 days ago) · « Renvoyer l'invitation » |
 * | M. Julien Girard (Oslo)     | +33612345678     | « Actif depuis le … » (the demo PORTAL account)          |
 * | Mme Élise Moreau (archived) | +33644556677     | « Accès retiré » · « Réinviter » disabled (archived)     |
 * | M. Bernard Giraud           | landline only    | disabled: « Ajoutez un numéro de mobile… »               |
 * | Mme Chloé Lambert           | +33655667788     | invitable by SMS, E-mail segment disabled (no e-mail)    |
 * | M. Lukas Weber              | +4915112345678   | disabled: « Ce numéro de mobile n'est pas encore pris en charge… » (foreign number labelled « mobile ») |
 *
 * Owner side (Portal API):
 *
 * - +33612345678 (PortalUserStory::DEMO_PHONE) is linked to **two** clinics:
 *   Paris (Julien Girard, owner of Oslo) and Lyon (Julien Girard, owner of
 *   Pixel), with an owner profile and the current terms accepted. Signing
 *   in gives a session straight away.
 * - +33622334455 (Antoine Roussel) has a pending invitation and **no
 *   account**: signing in with it demonstrates the phone onboarding
 *   (`invitation_pending` with the pending invitations and an onboarding
 *   grant). The link flow works too: `POST /invitations/lookup` with the
 *   token ROUSSEL_TOKEN (link `<PORTAL_PUBLIC_BASE_URL>/invitation#t=<token>`);
 *   PERRIN_TOKEN answers `410 invitation_expired`.
 * - +33644556677 (Élise Moreau) has an account whose only link was revoked
 *   when her file was archived: signed in, she sees no clinic.
 *
 * One-time codes: in dev (NOTIFICATION_DELIVERY=simulator) no SMS leaves;
 * the notification worker (`external-worker`, `make worker-start`) logs
 * every SMS body on the monolog channel `dev_sms`, in var/log/dev.log (the
 * project directory is mounted in the worker container):
 * `grep dev_sms var/log/dev.log | tail -n 5`.
 *
 * The rows are written with the ClientPortal factories (the dates of each
 * state are set as they would be after the fact); no message is sent.
 */
final class ClientPortalStatesStory extends Story
{
    public const string FONTAINE_ID = '019a3d00-0000-7000-8000-0000000000a1';
    public const string ROUSSEL_ID  = '019a3d00-0000-7000-8000-0000000000a2';
    public const string PERRIN_ID   = '019a3d00-0000-7000-8000-0000000000a3';
    public const string MOREAU_ID   = '019a3d00-0000-7000-8000-0000000000a4';
    public const string GIRAUD_ID   = '019a3d00-0000-7000-8000-0000000000a5';
    public const string LAMBERT_ID  = '019a3d00-0000-7000-8000-0000000000a6';
    public const string WEBER_ID    = '019a3d00-0000-7000-8000-0000000000a7';

    /** Julien Girard's file in Lyon (his Paris file comes from ConsultationDemoStory). */
    public const string GIRARD_LYON_ID = '019a3d00-0000-7000-8000-0000000000b1';

    /** Clear tokens of the demo invitation links (only their sha256 is stored). */
    public const string ROUSSEL_TOKEN = 'demo-portal-invitation-roussel-pending-0001';
    public const string PERRIN_TOKEN  = 'demo-portal-invitation-perrin-expired-00001';

    public const string ROUSSEL_PHONE = '+33622334455';
    public const string MOREAU_PHONE  = '+33644556677';

    private const string PARIS = ClinicDataStory::INDEPENDENT_CLINIC_ID;
    private const string LYON  = ClinicDataStory::GROUP_CLINIC_ID;

    private \DateTimeImmutable $now;

    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
        #[Autowire('%env(PORTAL_TERMS_VERSION)%')]
        private readonly string $termsVersion,
    ) {
    }

    public function build(): void
    {
        PortalUserStory::load();

        $this->now = $this->clock->now();

        $this->notInvited();
        $this->invited();
        $this->expired();
        $this->activeInTwoClinics();
        $this->revoked();
        $this->notInvitable();
    }

    private function notInvited(): void
    {
        $this->client(self::PARIS, self::FONTAINE_ID, 'madam', 'Camille', 'Fontaine', ['+33611223344', ContactLabel::MOBILE], 'camille.fontaine@free.fr', ['7 rue Lepic', '75018']);
        $this->pet(self::PARIS, self::FONTAINE_ID, 'Pistache', 'cat', 'female', 'Européen', '2019-05-14');
    }

    private function invited(): void
    {
        $this->client(self::PARIS, self::ROUSSEL_ID, 'mister', 'Antoine', 'Roussel', [self::ROUSSEL_PHONE, ContactLabel::MOBILE], 'antoine.roussel@gmail.com', ['42 boulevard de Clichy', '75018']);
        $this->pet(self::PARIS, self::ROUSSEL_ID, 'Ulysse', 'dog', 'male', 'Border collie', '2021-09-02');

        PortalInvitationEntityFactory::new()
            ->forClient(self::PARIS, self::ROUSSEL_ID, self::ROUSSEL_PHONE, 'antoine.roussel@gmail.com')
            ->sentAt($this->now->modify('-1 day'))
            ->withToken(self::ROUSSEL_TOKEN)
            ->create()
        ;
    }

    private function expired(): void
    {
        $this->client(self::PARIS, self::PERRIN_ID, 'madam', 'Laura', 'Perrin', ['+33633445566', ContactLabel::MOBILE], 'laura.perrin@orange.fr', ['3 rue Caulaincourt', '75018']);

        PortalInvitationEntityFactory::new()
            ->forClient(self::PARIS, self::PERRIN_ID, '+33633445566', 'laura.perrin@orange.fr')
            ->sentAt($this->now->modify('-10 days'))
            ->withToken(self::PERRIN_TOKEN)
            ->byEmail()
            ->create()
        ;
    }

    /** The demo owner account: Julien Girard, client in Paris and in Lyon. */
    private function activeInTwoClinics(): void
    {
        $userId = $this->portalUserId(PortalUserStory::DEMO_PHONE);
        $paris  = $this->parisGirard();

        $this->client(self::LYON, self::GIRARD_LYON_ID, 'mister', 'Julien', 'Girard', [PortalUserStory::DEMO_PHONE, ContactLabel::MOBILE], null, ['14 quai Saint-Vincent', '69001'], 'Lyon');
        $this->pet(self::LYON, self::GIRARD_LYON_ID, 'Pixel', 'cat', 'male', 'Chartreux', '2022-02-11');

        $this->linked($userId, self::PARIS, $paris, PortalUserStory::DEMO_PHONE, $this->now->modify('-61 days'));
        $this->linked($userId, self::LYON, self::GIRARD_LYON_ID, PortalUserStory::DEMO_PHONE, $this->now->modify('-12 days'));

        OwnerProfileEntityFactory::new()
            ->forUser($userId, 'Julien', 'Girard')
            ->acceptedTerms($this->termsVersion, $this->now->modify('-61 days'))
            ->create()
        ;
    }

    /** An archived client: the clinic withdrew her access when it archived the file. */
    private function revoked(): void
    {
        $this->client(self::PARIS, self::MOREAU_ID, 'madam', 'Élise', 'Moreau', [self::MOREAU_PHONE, ContactLabel::MOBILE], null, ['21 rue Damrémont', '75018'], archivedSince: '-30 days');

        $user = PortalUserFactory::new()->withPhone(self::MOREAU_PHONE)->create();
        $this->linked($user->getId()->toRfc4122(), self::PARIS, self::MOREAU_ID, self::MOREAU_PHONE, $this->now->modify('-90 days'), revokedAt: $this->now->modify('-30 days'));

        OwnerProfileEntityFactory::new()
            ->forUser($user->getId()->toRfc4122(), 'Élise', 'Moreau')
            ->withAddress('21 rue Damrémont', '75018', 'Paris')
            ->acceptedTerms($this->termsVersion, $this->now->modify('-90 days'))
            ->create()
        ;
    }

    /** The clients the sheet cannot invite, or not by e-mail. */
    private function notInvitable(): void
    {
        $this->client(self::PARIS, self::GIRAUD_ID, 'mister', 'Bernard', 'Giraud', ['+33145678910', ContactLabel::HOME], 'bernard.giraud@wanadoo.fr', ['60 rue Ordener', '75018']);
        $this->client(self::PARIS, self::LAMBERT_ID, 'madam', 'Chloé', 'Lambert', ['+33655667788', ContactLabel::MOBILE], null, ['11 rue Custine', '75018']);
        $this->client(self::PARIS, self::WEBER_ID, 'mister', 'Lukas', 'Weber', ['+4915112345678', ContactLabel::MOBILE], 'lukas.weber@web.de', ['5 rue des Saules', '75018']);
    }

    /**
     * An accepted invitation and the confirmed link it made (revoked later
     * when `$revokedAt` is given), with the dates of the acceptance.
     */
    private function linked(string $userId, string $clinicId, string $clientId, string $phone, \DateTimeImmutable $acceptedAt, ?\DateTimeImmutable $revokedAt = null): void
    {
        PortalInvitationEntityFactory::new()
            ->forClient($clinicId, $clientId, $phone, null)
            ->sentAt($acceptedAt->modify('-1 day'))
            ->acceptedBy($userId, $acceptedAt)
            ->create()
        ;

        $link = ClientLinkEntityFactory::new()->between($userId, $clinicId, $clientId)->linkedAt($acceptedAt);
        (null === $revokedAt ? $link : $link->revoked($revokedAt))->create();
    }

    /**
     * @param array{0: string, 1: ContactLabel} $phone
     * @param array{0: string, 1: string}       $address street and postal code
     */
    private function client(
        string $clinicId,
        string $id,
        string $civility,
        string $firstName,
        string $lastName,
        array $phone,
        ?string $email,
        array $address,
        string $city = 'Paris',
        ?string $archivedSince = null,
    ): void {
        $since   = $this->now->modify('-3 years')->format('Y-m-d H:i:s');
        $factory = ClientEntityFactory::new()
            ->withId($id)
            ->withClinicId($clinicId)
            ->withName($firstName, $lastName)
            ->withCivility($civility)
            ->since($since, null === $archivedSince ? null : $this->now->modify($archivedSince)->format('Y-m-d H:i:s'))
            ->withPostalAddress(streetLine1: $address[0], city: $city, countryCode: 'FR', postalCode: $address[1])
            ->withCommunicationPreferences('sms', ['appointment_reminders' => ['sms']], $since)
        ;
        (null === $archivedSince ? $factory->active() : $factory->archived())->create();

        ContactMethodEntityFactory::new()->forClient($id)->phone($phone[0])->with(['label' => $phone[1]])->primary()->create();

        if (null !== $email) {
            ContactMethodEntityFactory::new()->forClient($id)->email($email)->home()->primary()->create();
        }
    }

    private function pet(string $clinicId, string $clientId, string $name, string $species, string $sex, string $breed, string $birthDate): void
    {
        $this->commandBus->dispatch(new CreateAnimal(
            clinicId: $clinicId,
            name: $name,
            species: $species,
            sex: $sex,
            reproductiveStatus: 'unknown',
            isMixedBreed: false,
            breedName: $breed,
            birthDate: $birthDate,
            color: null,
            photoUrl: null,
            microchipNumber: null,
            tattooNumber: null,
            passportNumber: null,
            registryType: 'none',
            registryNumber: null,
            registryReference: null,
            lifeStatus: 'alive',
            deceasedAt: null,
            missingSince: null,
            transferStatus: 'none',
            soldAt: null,
            givenAt: null,
            auxiliaryContactFirstName: null,
            auxiliaryContactLastName: null,
            auxiliaryContactPhoneNumber: null,
            primaryOwnerClientId: $clientId,
            secondaryOwnerClientIds: [],
        ));
    }

    private function portalUserId(string $phone): string
    {
        $id = $this->connection->fetchOne("SELECT BIN_TO_UUID(id) FROM identity_access__users WHERE type = 'PORTAL' AND phone = ?", [$phone]);
        \assert(\is_string($id), 'PortalUserStory creates the demo owner account.');

        return $id;
    }

    /** Julien Girard of Paris, owner of Oslo, written by ConsultationDemoStory with the demo phone. */
    private function parisGirard(): string
    {
        $id = $this->connection->fetchOne(
            'SELECT BIN_TO_UUID(c.id) FROM client__clients c INNER JOIN client__contact_methods m ON m.client_id = c.id
             WHERE c.clinic_id = ? AND c.last_name = ? AND m.value = ? LIMIT 1',
            [Uuid::fromString(self::PARIS)->toBinary(), 'Girard', PortalUserStory::DEMO_PHONE],
        );
        \assert(\is_string($id), 'ConsultationDemoStory creates Julien Girard with the demo phone.');

        return $id;
    }
}
