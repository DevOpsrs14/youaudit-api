<?php

namespace App\Controllers\Api\V1;

use App\Services\SiteLocationService;

class LocationController extends BaseApiController
{
    public function bySite($siteId = null)
    {
        $userId = (int) ($this->request->authUserId ?? 0);
        $siteId = (int) $siteId;

        if ($userId <= 0) {
            return $this->error(
                'Authentication required.',
                401
            );
        }

        if ($siteId <= 0) {
            return $this->error(
                'Invalid site.',
                400
            );
        }

        $siteLocationService = new SiteLocationService();

        $locations = $siteLocationService
            ->getLocationsForUserAndSite($userId, $siteId);

        if ($locations === null) {
            /*
             * Intentionally use the same response whether the Site
             * does not exist or belongs to another account.
             */
            return $this->error(
                'Site not found.',
                404
            );
        }

        return $this->success(
            $locations,
            'Locations loaded successfully.'
        );
    }
}
