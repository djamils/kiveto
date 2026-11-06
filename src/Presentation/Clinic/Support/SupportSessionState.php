<?php

declare(strict_types=1);

namespace App\Presentation\Clinic\Support;

use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * What the clinic host keeps in the session while a support session runs:
 * the banner's words, the mode, the expiry, who opened it.
 */
final readonly class SupportSessionState
{
    public const string KEY = 'support.session_id';

    private const string DATA = 'support.data';

    /**
     * @param array{sessionId: string, clinicId: string, clinicName: string, userName: string, adminId: string, adminName: string, mode: string, modeLabel: string, expiresAt: int} $data
     */
    private function __construct(
        public array $data,
    ) {
    }

    /**
     * @param array{sessionId: string, clinicId: string, clinicName: string, userName: string, adminId: string, adminName: string, mode: string, modeLabel: string, expiresAt: int} $data
     */
    public static function open(SessionInterface $session, array $data): void
    {
        $session->set(self::KEY, $data['sessionId']);
        $session->set(self::DATA, $data);
    }

    public static function of(SessionInterface $session): ?self
    {
        $data = $session->get(self::DATA);

        if (!\is_string($session->get(self::KEY)) || !\is_array($data)) {
            return null;
        }

        $text = [];
        foreach (['sessionId', 'clinicId', 'clinicName', 'userName', 'adminId', 'adminName', 'mode', 'modeLabel'] as $key) {
            if (!\is_string($data[$key] ?? null)) {
                return null;
            }

            $text[$key] = $data[$key];
        }

        if (!\is_int($data['expiresAt'] ?? null)) {
            return null;
        }

        return new self([
            'sessionId'  => $text['sessionId'],
            'clinicId'   => $text['clinicId'],
            'clinicName' => $text['clinicName'],
            'userName'   => $text['userName'],
            'adminId'    => $text['adminId'],
            'adminName'  => $text['adminName'],
            'mode'       => $text['mode'],
            'modeLabel'  => $text['modeLabel'],
            'expiresAt'  => $data['expiresAt'],
        ]);
    }

    public function sessionId(): string
    {
        return $this->data['sessionId'];
    }

    public function isReadOnly(): bool
    {
        return 'read' === $this->data['mode'];
    }

    public function isOverAt(int $timestamp): bool
    {
        return $timestamp >= $this->data['expiresAt'];
    }
}
