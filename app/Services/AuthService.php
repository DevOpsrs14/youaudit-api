<?php

namespace App\Services;

use App\Models\UserModel;

class AuthService
{
    private UserModel $userModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
    }

    /**
     * Authenticate an existing YouAudit user.
     *
     * During the legacy coexistence period:
     * - New app sends the plain password over HTTPS.
     * - Existing database still contains the legacy MD5 hash.
     * - We verify MD5 server-side.
     * - We do NOT modify the stored legacy password yet.
     */
    public function authenticate(string $username, string $password): array
    {
        $username = trim($username);

        $user = $this->userModel->findActiveByUsername($username);

        if ($user === null) {
            return [
                'success' => false,
                'reason'  => 'invalid_credentials',
            ];
        }

        $legacyHash = strtolower((string) $user['password']);
        $submittedHash = md5($password);

        if (!hash_equals($legacyHash, $submittedHash)) {
            return [
                'success' => false,
                'reason'  => 'invalid_credentials',
            ];
        }

        // Preserve the existing YouAudit application access rule.
        if ((int) $user['youaudit'] !== 1) {
            return [
                'success' => false,
                'reason'  => 'access_denied',
            ];
        }

        // Never allow the stored password/hash to leave this service.
        unset($user['password']);

        return [
            'success' => true,
            'user'    => $user,
        ];
    }
}
