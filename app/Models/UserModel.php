<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table      = 'users';
    protected $primaryKey = 'id';
    protected $returnType = 'array';

    protected $useAutoIncrement = true;

    // We are not writing to users during the first login implementation.
    protected $allowedFields = [];

    /**
     * Find an active legacy YouAudit user by username.
     *
     * Password verification is deliberately NOT performed here.
     * AuthService will handle that separately.
     */
    public function findActiveByUsername(string $username): ?array
    {
        $user = $this->select([
                'id',
                'username',
                'password',
                'firstname',
                'lastname',
                'nickname',
                'level_id',
                'account_id',
                'photo_id',
                'active',
                'is_owner',
                'push_notification',
                'youaudit',
                'youcheck',
            ])
            ->where('username', $username)
            ->where('active', 1)
            ->first();

        return $user ?: null;
    }
}
