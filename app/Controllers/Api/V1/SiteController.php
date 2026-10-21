<?php

namespace App\Controllers\Api\V1;

use App\Services\SiteLocationService;

class SiteController extends BaseApiController
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

        $siteLocationService = new SiteLocationService();

        $sites = $siteLocationService->getSitesForUser($userId);

        if ($sites === null) {
            return $this->error(
                'Site data not available.',
                404
            );
        }

        return $this->success(
            $sites,
            'Sites loaded successfully.'
        );
    }
}
