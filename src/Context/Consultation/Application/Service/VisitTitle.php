<?php

declare(strict_types=1);

namespace App\Context\Consultation\Application\Service;

/**
 * The short title a visit is listed under (« Vaccination et bilan annuel »).
 *
 * One rule for every reader: the VetApp dossier visits and the Portal
 * timeline. The first motif is kept as is, the following ones are lowercased
 * on their first letter and joined with « et »; without any motif the chief
 * complaint stands in, stripped of its trailing dots and spaces; otherwise
 * « Consultation ».
 */
final class VisitTitle
{
    /**
     * @param list<string> $motifs
     */
    public static function of(array $motifs, ?string $chiefComplaint): string
    {
        if ([] !== $motifs) {
            $rest = array_map(
                static fn (string $motif): string => mb_strtolower(mb_substr($motif, 0, 1)) . mb_substr($motif, 1),
                \array_slice($motifs, 1),
            );

            return implode(' et ', [$motifs[0], ...$rest]);
        }

        return null === $chiefComplaint ? 'Consultation' : rtrim($chiefComplaint, '. ');
    }
}
