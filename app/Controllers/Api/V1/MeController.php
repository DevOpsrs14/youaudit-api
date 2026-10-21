<?php

namespace App\Controllers\Api\V1;

use App\Models\UserModel;

class MeController extends BaseApiController
{
    public function index()
    {
        $userId = (int) $this->request->authUserId;

        $userModel = new UserModel();

        $user = $userModel
            ->select([
                'id',
                'username',
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
            ->where('id', $userId)
            ->where('active', 1)
            ->first();

        if (!$user) {
            return $this->error(
                'User not found or inactive',
                401
            );
        }

        return $this->success([
            'user' => $user,
        ], 'Authenticated user retrieved');
    }
}
