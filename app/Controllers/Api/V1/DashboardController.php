<?php

namespace App\Controllers\Api\V1;

use App\Services\DashboardService;

class DashboardController extends BaseApiController
{
    public function index()
    {
        $userId = (int) ($this->request->authUserId ?? 0);

        if ($userId <= 0) {
            return $this->error(
                'Authentication required.',
                401
            );
        }

        $dashboardService = new DashboardService();

        $dashboard = $dashboardService->getDashboardForUser($userId);

        if ($dashboard === null) {
            return $this->error(
                'Dashboard data not available.',
                404
            );
        }

        return $this->success(
            $dashboard,
            'Dashboard loaded successfully.'
        );
    }
}
