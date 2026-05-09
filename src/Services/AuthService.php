<?php

declare(strict_types=1);

namespace Involve\Services;

use Involve\Repositories\AuthRepository;

final class AuthService
{
    public function __construct(private readonly AuthRepository $users)
    {
    }

    /**
     * @return array{ok: bool, user?: array<string, mixed>, error?: string, pending_verification_email?: string}
     */
    public function authenticate(string $email, string $password): array
    {
        $candidate = $this->users->findUserByEmail($email);
        if (!$candidate || !password_verify($password, (string) ($candidate['password_hash'] ?? ''))) {
            return ['ok' => false, 'error' => 'invalid_credentials'];
        }

        $accountStatus = (string) ($candidate['account_status'] ?? 'active');
        if ($accountStatus === 'suspended') {
            return ['ok' => false, 'error' => 'suspended'];
        }

        if ($accountStatus === 'banned') {
            return ['ok' => false, 'error' => 'banned'];
        }

        if ((int) ($candidate['email_verified'] ?? 1) === 0) {
            return [
                'ok' => false,
                'error' => 'email_unverified',
                'pending_verification_email' => $email,
            ];
        }

        return ['ok' => true, 'user' => $candidate];
    }
}
