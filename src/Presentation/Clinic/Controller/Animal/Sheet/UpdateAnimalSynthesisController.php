<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Controller\Animal\Sheet;

use App\Context\Animal\Application\Command\AddMedicalAlert\AddMedicalAlert;
use App\Context\Animal\Application\Command\RemoveMedicalAlert\RemoveMedicalAlert;
use App\Shared\Application\Bus\CommandBusInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Synthèse médicale » saved in one go: the items added to its four columns
 * (problems, long-term treatments, allergies, behaviour) and the ones removed.
 */
#[Route('/animals/{id}/synthesis', name: 'clinic_animals_synthesis_update', methods: ['POST'])]
final class UpdateAnimalSynthesisController extends AbstractAnimalSheetAction
{
    private const array KINDS = ['pb' => 'CHRONIC_CONDITION', 'trt' => 'LONG_TERM_TREATMENT', 'all' => 'ALLERGY', 'comp' => 'BEHAVIOR'];

    public function __construct(
        private readonly CommandBusInterface $commandBus,
    ) {
    }

    public function __invoke(string $id, Request $request): Response
    {
        $this->guard($request);

        $animal = $this->animal($id);

        if (!$this->isOpen($animal)) {
            return $this->refuseClosedFile($id);
        }

        $failed = 0;

        foreach ($this->strings($request, 'remove') as $alertId) {
            $removed = $this->attempt(fn () => $this->commandBus->dispatch(new RemoveMedicalAlert(
                clinicId: $animal->clinicId,
                animalId: $animal->id,
                alertId: $alertId,
            )));
            $failed += $removed ? 0 : 1;
        }

        foreach ($this->additions($request) as [$kind, $label, $note]) {
            $added = $this->attempt(fn () => $this->commandBus->dispatch(new AddMedicalAlert(
                clinicId: $animal->clinicId,
                animalId: $animal->id,
                kind: $kind,
                label: $label,
                note: $note,
            )));
            $failed += $added ? 0 : 1;
        }

        if (0 === $failed) {
            $this->addFlash('success', 'Synthèse médicale enregistrée.');
        } else {
            $this->addFlash('error', 'Une partie de la synthèse n’a pas pu être enregistrée : un élément est déjà au dossier ou dépasse 120 caractères.');
        }

        return $this->backToSheet($id, 'cSyn');
    }

    /**
     * @return list<array{0: string, 1: string, 2: ?string}> kind, label, note
     */
    private function additions(Request $request): array
    {
        $added = $request->request->all('add');
        $items = [];

        foreach (self::KINDS as $column => $kind) {
            $entries = \is_array($added[$column] ?? null) ? $added[$column] : [];

            foreach ($entries as $entry) {
                if (!\is_array($entry) || !\is_string($entry['l'] ?? null) || '' === trim($entry['l'])) {
                    continue;
                }

                $note    = \is_string($entry['d'] ?? null) && '' !== trim($entry['d']) ? trim($entry['d']) : null;
                $items[] = [$kind, trim($entry['l']), $note];
            }
        }

        return $items;
    }

    /**
     * @return list<string>
     */
    private function strings(Request $request, string $field): array
    {
        return array_values(array_filter(
            $request->request->all($field),
            static fn (mixed $value): bool => \is_string($value) && '' !== $value,
        ));
    }
}
