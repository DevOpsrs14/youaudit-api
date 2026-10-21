<?php

namespace App\Services;

use Config\Database;

class SiteLocationService
{
    private $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    /**
     * Resolve the authenticated user's current legacy account.
     *
     * V1 must never trust account_id supplied by the client.
     */
    private function getAccountIdForUser(int $userId): ?int
    {
        $user = $this->db->table('users')
            ->select('account_id')
            ->where('id', $userId)
            ->where('active', 1)
            ->where('youaudit', 1)
            ->get()
            ->getRowArray();

        if (!$user || (int) $user['account_id'] <= 0) {
            return null;
        }

        return (int) $user['account_id'];
    }

    /**
     * Return active Sites available to the authenticated user's account.
     */
    public function getSitesForUser(int $userId): ?array
    {
        $accountId = $this->getAccountIdForUser($userId);

        if ($accountId === null) {
            return null;
        }

        $rows = $this->db->table('sites')
            ->select([
                'id',
                'name',
                'barcode',
            ])
            ->where('account_id', $accountId)
            ->where('active', 1)
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();

        return array_map(
            static function (array $row): array {
                return [
                    'id'      => (int) $row['id'],
                    'name'    => $row['name'],
                    'barcode' => $row['barcode'],
                ];
            },
            $rows
        );
    }

    /**
     * Return active Locations belonging to an active Site owned by
     * the authenticated user's account.
     *
     * null = Site is not accessible / does not exist.
     * []   = Valid Site with no active Locations.
     */
    public function getLocationsForUserAndSite(
        int $userId,
        int $siteId
    ): ?array {
        $accountId = $this->getAccountIdForUser($userId);

        if ($accountId === null || $siteId <= 0) {
            return null;
        }

        /*
         * Validate the Site against the authenticated account first.
         * This prevents cross-account Site IDs being used to enumerate
         * Location data.
         */
        $site = $this->db->table('sites')
            ->select('id')
            ->where('id', $siteId)
            ->where('account_id', $accountId)
            ->where('active', 1)
            ->get()
            ->getRowArray();

        if (!$site) {
            return null;
        }

        /*
         * Legacy locations.site_id is varchar while sites.id is integer.
         * We intentionally leave the legacy schema unchanged.
         */
        $rows = $this->db->table('locations')
            ->select([
                'id',
                'name',
                'barcode',
                'site_id',
                'location_type',
            ])
            ->where('account_id', $accountId)
            ->where('site_id', (string) $siteId)
            ->where('active', 1)
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();

        return array_map(
            static function (array $row): array {
                return [
                    'id'            => (int) $row['id'],
                    'name'          => $row['name'],
                    'barcode'       => $row['barcode'],
                    'site_id'       => (int) $row['site_id'],
                    'location_type' => $row['location_type'] !== null
                        ? (int) $row['location_type']
                        : null,
                ];
            },
            $rows
        );
    }
}
