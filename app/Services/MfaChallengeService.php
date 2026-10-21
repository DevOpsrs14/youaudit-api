<?php

namespace App\Services;

use Config\Database;

class MfaChallengeService
{
    private $db;

    private const CHALLENGE_EXPIRY_MINUTES = 10;
    private const MAX_ATTEMPTS = 5;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    public function createChallenge(
        int $userId,
        string $type,
        ?string $method = null
    ): array {
        if (!in_array($type, ['setup', 'verification'], true)) {
            throw new \InvalidArgumentException(
                'Invalid MFA challenge type.'
            );
        }

        if (
            $method !== null &&
            !in_array($method, ['authenticator', 'email', 'sms'], true)
        ) {
            throw new \InvalidArgumentException(
                'Invalid MFA method.'
            );
        }

        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);

        $createdAt = date('Y-m-d H:i:s');
        $expiresAt = date(
            'Y-m-d H:i:s',
            strtotime('+' . self::CHALLENGE_EXPIRY_MINUTES . ' minutes')
        );

        $this->db->table('mfa_challenges')->insert([
            'user_id'        => $userId,
            'challenge_hash' => $tokenHash,
            'challenge_type' => $type,
            'method'         => $method,
            'attempt_count'  => 0,
            'created_at'     => $createdAt,
            'expires_at'     => $expiresAt,
            'completed_at'   => null,
        ]);

        if ($this->db->affectedRows() !== 1) {
            throw new \RuntimeException(
                'Unable to create MFA challenge.'
            );
        }

        return [
            'challenge_token' => $token,
            'expires_at'      => $expiresAt,
        ];
    }

    public function validateChallenge(string $token): ?array
    {
        $token = trim($token);

        if ($token === '') {
            return null;
        }

        $tokenHash = hash('sha256', $token);
        $now = date('Y-m-d H:i:s');

        $challenge = $this->db
            ->table('mfa_challenges')
            ->where('challenge_hash', $tokenHash)
            ->where('completed_at', null)
            ->where('expires_at >', $now)
            ->where('attempt_count <', self::MAX_ATTEMPTS)
            ->get()
            ->getRowArray();

        return $challenge ?: null;
    }

    public function setSetupMethod(
        int $challengeId,
        string $method
    ): bool {
        if (!in_array(
            $method,
            ['authenticator', 'email', 'sms'],
            true
        )) {
            throw new \InvalidArgumentException(
                'Invalid MFA method.'
            );
        }

        $now = date('Y-m-d H:i:s');

        $this->db
            ->table('mfa_challenges')
            ->where('id', $challengeId)
            ->where('challenge_type', 'setup')
            ->where('completed_at', null)
            ->where('expires_at >', $now)
            ->update([
                'method' => $method,
            ]);

        $challenge = $this->db
            ->table('mfa_challenges')
            ->where('id', $challengeId)
            ->where('challenge_type', 'setup')
            ->where('method', $method)
            ->where('completed_at', null)
            ->where('expires_at >', $now)
            ->get()
            ->getRowArray();

        return $challenge !== null;
    }

    public function verifyOtp(
        string $challengeToken,
        string $otp
    ): array {
        $challenge = $this->validateChallenge(
            $challengeToken
        );

        if (!$challenge) {
            return [
                'success' => false,
                'reason'  => 'invalid_challenge',
            ];
        }

        if (!in_array(
            $challenge['method'],
            ['email', 'sms'],
            true
        )) {
            return [
                'success' => false,
                'reason'  => 'invalid_method',
            ];
        }

        if (
            empty($challenge['otp_hash']) ||
            empty($challenge['otp_expires_at'])
        ) {
            return [
                'success' => false,
                'reason'  => 'otp_not_generated',
            ];
        }

        $now = date('Y-m-d H:i:s');

        if ($challenge['otp_expires_at'] <= $now) {
            return [
                'success' => false,
                'reason'  => 'otp_expired',
            ];
        }

        $submittedHash = hash('sha256', $otp);

        if (!hash_equals(
            $challenge['otp_hash'],
            $submittedHash
        )) {
            $this->incrementAttempt(
                (int) $challenge['id']
            );

            $attempts = (int) $challenge['attempt_count'] + 1;

            return [
                'success'            => false,
                'reason'             => 'invalid_otp',
                'attempts_remaining' => max(
                    0,
                    self::MAX_ATTEMPTS - $attempts
                ),
            ];
        }

        return [
            'success'   => true,
            'challenge' => $challenge,
        ];
    }

    public function incrementAttempt(int $challengeId): void
    {
        $this->db->query(
            'UPDATE mfa_challenges
             SET attempt_count = attempt_count + 1
             WHERE id = ?
               AND completed_at IS NULL
               AND attempt_count < ?',
            [
                $challengeId,
                self::MAX_ATTEMPTS,
            ]
        );
    }

    public function completeChallenge(int $challengeId): bool
    {
        $now = date('Y-m-d H:i:s');

        $this->db
            ->table('mfa_challenges')
            ->where('id', $challengeId)
            ->where('completed_at', null)
            ->update([
                'completed_at' => $now,
            ]);

        return $this->db->affectedRows() === 1;
    }
}
