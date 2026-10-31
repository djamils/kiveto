<?php

declare(strict_types=1);

namespace App\Context\ClientPortal\Application\Service;

use App\Context\ClientPortal\Application\Port\BookletFileRepositoryInterface;
use App\Context\ClientPortal\Application\Port\BookletRendererInterface;
use App\Context\ClientPortal\Application\Port\ClinicProfileInterface;
use App\Context\ClientPortal\Application\Port\DocumentFileJobsInterface;
use App\Context\ClientPortal\Application\Port\PetDirectoryInterface;
use App\Context\ClientPortal\Application\Port\VaccinationBooklet;
use App\Context\ClientPortal\Application\Port\VaccinationRecord;
use App\Context\ClientPortal\Application\Port\VaccinationRecordsInterface;
use App\Context\ClientPortal\Domain\Exception\PetNotFoundException;
use App\Shared\Application\Document\DocumentRenderingFailed;
use App\Shared\Domain\Storage\FileStorageInterface;
use App\Shared\Domain\Time\ClockInterface;

/**
 * The vaccination booklet of an animal, rendered on demand and cached as
 * long as what it prints does not change: its key is the hash of every
 * render input (vaccinations, identity, clinic header, template version).
 * A new key replaces the animal's previous booklet, deleted later so that a
 * URL signed just before still serves it.
 *
 * Called directly, without a bus, and only once the account was shown to
 * see the pet (GetOwnerPet). Writing the cache from a read is an accepted
 * technical side effect; the render (Gotenberg) runs outside any transaction.
 */
final readonly class VaccinationBookletFiles
{
    public const string KEY_PREFIX = 'client_portal/booklets/';

    /** Bumped whenever the booklet template changes what it prints. */
    public const int TEMPLATE_VERSION = 1;

    public function __construct(
        private VaccinationRecordsInterface $vaccinations,
        private PetDirectoryInterface $pets,
        private ClinicProfileInterface $clinics,
        private BookletRendererInterface $renderer,
        private FileStorageInterface $storage,
        private BookletFileRepositoryInterface $booklets,
        private DocumentFileJobsInterface $fileJobs,
        private ClockInterface $clock,
        private int $templateVersion = self::TEMPLATE_VERSION,
    ) {
    }

    /**
     * @throws PetNotFoundException    when the animal is unknown to the clinic
     * @throws DocumentRenderingFailed when the PDF service is down or refuses the booklet
     *
     * @return string the storage key of the animal's current booklet
     */
    public function ensure(string $clinicId, string $animalId): string
    {
        $pet = $this->pets->identityOf($clinicId, $animalId) ?? throw new PetNotFoundException($animalId);

        $booklet = new VaccinationBooklet(
            $this->clinics->documentHeaderOf($clinicId),
            $pet,
            $this->vaccinations->vaccinationsOf($clinicId, $animalId),
        );

        $key      = self::KEY_PREFIX . $animalId . '-' . hash('sha256', $this->canonicalJson($booklet)) . '.pdf';
        $previous = $this->booklets->currentKey($clinicId, $animalId);

        if ($key === $previous) {
            return $key;
        }

        $this->storage->storeAt($key, $this->renderer->render($booklet));
        $this->booklets->saveCurrent($clinicId, $animalId, $key, $this->clock->now());

        if (null !== $previous) {
            $this->fileJobs->deleteFileLater($previous);
        }

        return $key;
    }

    private function canonicalJson(VaccinationBooklet $booklet): string
    {
        $date = static fn (?\DateTimeImmutable $day): ?string => $day?->format('Y-m-d');
        $pet  = $booklet->pet;

        return json_encode([
            'template' => $this->templateVersion,
            'clinic'   => $booklet->clinic,
            'pet'      => [
                'name'         => $pet->name,
                'species'      => $pet->species,
                'breed'        => $pet->breedName,
                'isMixedBreed' => $pet->isMixedBreed,
                'sex'          => $pet->sex,
                'birthDate'    => $date($pet->birthDate),
                'chip'         => $pet->microchipNumber,
            ],
            'vaccinations' => array_map(static fn (VaccinationRecord $record): array => [
                'id'             => $record->id,
                'label'          => $record->label,
                'administeredOn' => $date($record->administeredOn),
                'batchNumber'    => $record->batchNumber,
                'nextDueOn'      => $date($record->nextDueOn),
            ], $booklet->vaccinations),
        ], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
    }
}
