<?php

namespace App\Services;

use Config\Database;

class SessionService
{
    private $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    public function createSession(
        int $userId,
        ?string $deviceName = null,
        ?string $platform = null,
        ?string $appVersion = null
    ): array {
        // 32 random bytes = 256-bit bearer token
        $token = bin2hex(random_bytes(32));

        // Never store the raw bearer token in DB
        $tokenHash = hash('sha256', $token);

        $createdAt = date('Y-m-d H:i:s');
        $expiresAt = date(
            'Y-m-d H:i:s',
            strtotime('+30 days')
        );

        $this->db->table('user_sessions')->insert([
            'user_id'      => $userId,
            'token_hash'   => $tokenHash,
            'device_name'  => $deviceName,
            'platform'     => $platform,
            'app_version'  => $appVersion,
            'created_at'   => $createdAt,
            'last_used_at' => $createdAt,
            'expires_at'   => $expiresAt,
            'revoked_at'   => null,
        ]);

        if ($this->db->affectedRows() !== 1) {
            throw new \RuntimeException(
                'Unable to create authentication session.'
            );
        }

        return [
            'token'      => $token,
            'expires_at' => $expiresAt,
        ];
    }

    public function validateToken(string $token): ?array
    {
        $token = trim($token);

        if ($token === '') {
            return null;
        }

        $tokenHash = hash('sha256', $token);
        $now = date('Y-m-d H:i:s');

        $session = $this->db
            ->table('user_sessions')
            ->where('token_hash', $tokenHash)
            ->where('revoked_at', null)
            ->where('expires_at >', $now)
            ->get()
            ->getRowArray();

        if (!$session) {
            return null;
        }

        $this->db
            ->table('user_sessions')
            ->where('id', $session['id'])
            ->update([
                'last_used_at' => $now,
            ]);

        $session['last_used_at'] = $now;

        return $session;
    }

    public function revokeSession(int $sessionId): bool
    {
        $now = date('Y-m-d H:i:s');

        $this->db
            ->table('user_sessions')
            ->where('id', $sessionId)
            ->where('revoked_at', null)
            ->update([
                'revoked_at' => $now,
            ]);

        return $this->db->affectedRows() === 1;
    }
}
