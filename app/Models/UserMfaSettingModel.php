<?php

namespace App\Models;

use CodeIgniter\Model;

class UserMfaSettingModel extends Model
{
    protected $table      = 'user_mfa_settings';
    protected $primaryKey = 'id';
    protected $returnType = 'array';

    protected $allowedFields = [
        'user_id',
        'is_enabled',
        'preferred_method',
        'email',
        'mobile_number',
        'authenticator_secret',
        'authenticator_verified_at',
        'created_at',
        'updated_at',
    ];

    public function findByUserId(int $userId): ?array
    {
        $setting = $this
            ->where('user_id', $userId)
            ->first();

        return $setting ?: null;
    }
}
