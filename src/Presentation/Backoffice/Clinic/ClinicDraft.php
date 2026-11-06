<?php

declare(strict_types=1);

namespace App\Presentation\Backoffice\Clinic;

use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * The clinic being created (B3–B5), kept in the session between the three
 * steps: the company, the establishment, the team, the plan.
 */
final class ClinicDraft
{
    private const string SESSION_KEY = 'console.clinic_draft';

    /**
     * @param array<string, string>                                                         $company  siren, siret, legalName, naf, street, postalCode, city, vat, vatCheck
     * @param array<string, string>                                                         $place    name, slug, street, postalCode, city, phone, email, timeZone, parallelSoftware, sameAddress
     * @param list<array{firstName: string, lastName: string, email: string, role: string}> $team
     * @param list<string>                                                                  $features
     */
    public function __construct(
        public array $company = [],
        public array $place = [],
        public array $team = [],
        public string $plan = 'pilot',
        public array $features = [],
    ) {
    }

    public static function from(SessionInterface $session): self
    {
        $data = $session->get(self::SESSION_KEY);

        if (!\is_array($data)) {
            return new self();
        }

        return new self(
            company: self::strings($data['company'] ?? []),
            place: self::strings($data['place'] ?? []),
            team: self::team($data['team'] ?? []),
            plan: \is_string($data['plan'] ?? null) ? $data['plan'] : 'pilot',
            features: array_values(array_filter(\is_array($data['features'] ?? null) ? $data['features'] : [], is_string(...))),
        );
    }

    public function store(SessionInterface $session): void
    {
        $session->set(self::SESSION_KEY, [
            'company'  => $this->company,
            'place'    => $this->place,
            'team'     => $this->team,
            'plan'     => $this->plan,
            'features' => $this->features,
        ]);
    }

    public static function clear(SessionInterface $session): void
    {
        $session->remove(self::SESSION_KEY);
    }

    public function hasEstablishment(): bool
    {
        return '' !== ($this->company['siret'] ?? '') && '' !== ($this->place['name'] ?? '') && '' !== ($this->place['slug'] ?? '');
    }

    public function hasTeam(): bool
    {
        return [] !== $this->team;
    }

    public function veterinarians(): int
    {
        return \count(array_filter($this->team, static fn (array $person): bool => 'VETERINARY' === $person['role']));
    }

    /**
     * @param mixed $value
     *
     * @return array<string, string>
     */
    private static function strings($value): array
    {
        $strings = [];
        foreach (\is_array($value) ? $value : [] as $key => $item) {
            if (\is_string($key) && \is_string($item)) {
                $strings[$key] = $item;
            }
        }

        return $strings;
    }

    /**
     * @param mixed $value
     *
     * @return list<array{firstName: string, lastName: string, email: string, role: string}>
     */
    private static function team($value): array
    {
        $team = [];
        foreach (\is_array($value) ? $value : [] as $person) {
            if (\is_array($person) && \is_string($person['firstName'] ?? null) && \is_string($person['lastName'] ?? null) && \is_string($person['email'] ?? null) && \is_string($person['role'] ?? null)) {
                $team[] = ['firstName' => $person['firstName'], 'lastName' => $person['lastName'], 'email' => $person['email'], 'role' => $person['role']];
            }
        }

        return $team;
    }
}
