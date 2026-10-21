<?php

namespace App\Controllers\Api\V1;

class HealthController extends BaseApiController
{
    public function index()
    {
        return $this->success([
            'service' => 'youaudit-api',
        ], 'API is healthy');
    }
}
