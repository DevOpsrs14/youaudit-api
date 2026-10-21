<?php

namespace App\Services;

use Config\Database;

class AuthorizationService
{
    private $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    /**
     * Check whether a user has a legacy named permission.
     *
     * Levels 1-4 use the existing database permission mapping.
     * AppOnly roles 5, 6 and 7 have separate verified mobile rules.
     */
    public function hasLegacyPermission(
        int $userId,
        string $permissionName
    ): bool {
        if ($userId <= 0 || $permissionName === '') {
            return false;
        }

        $row = $this->db->table('users u')
            ->select('p.id')
            ->join('levels l', 'l.id = u.level_id', 'inner')
            ->join(
                'levels_permissions_link lpl',
                'lpl.level_id = l.id',
                'inner'
            )
            ->join(
                'permissions p',
                'p.id = lpl.permission_id',
                'inner'
            )
            ->where('u.id', $userId)
            ->where('u.active', 1)
            ->where('p.name', $permissionName)
            ->limit(1)
            ->get()
            ->getRowArray();

        return $row !== null;
    }

    /**
     * Return all legacy named permissions assigned to the user's level.
     */
    public function getLegacyPermissionsForUser(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $rows = $this->db->table('users u')
            ->select('p.name')
            ->join('levels l', 'l.id = u.level_id', 'inner')
            ->join(
                'levels_permissions_link lpl',
                'lpl.level_id = l.id',
                'inner'
            )
            ->join(
                'permissions p',
                'p.id = lpl.permission_id',
                'inner'
            )
            ->where('u.id', $userId)
            ->where('u.active', 1)
            ->orderBy('p.name', 'ASC')
            ->get()
            ->getResultArray();

        $permissions = [];

        foreach ($rows as $row) {
            if (!empty($row['name'])) {
                $permissions[] = $row['name'];
            }
        }

        return array_values(array_unique($permissions));
    }

    /**
     * Check a V1 capability.
     *
     * Unknown or unverified capabilities are denied by default.
     *
     * The API remains the security boundary.
     * Swift/Web can use capabilities for UI visibility,
     * but must not be trusted to enforce authorization.
     */
    public function can(int $userId, string $capability): bool
    {
        if ($userId <= 0 || $capability === '') {
            return false;
        }

        $user = $this->getActiveYouAuditUser($userId);

        if ($user === null) {
            return false;
        }

        $levelId = (int) $user['level_id'];

        switch ($capability) {
            case 'items.view':
                return $this->canViewItems($userId, $levelId);

            case 'items.add':
                return $this->canAddItems($userId, $levelId);

            case 'items.edit':
                return $this->canEditItems($userId, $levelId);

            case 'items.archive':
                return $this->canArchiveItems($userId, $levelId);

            default:
                return false;
        }
    }

    /**
     * Return the currently verified V1 capability set.
     *
     * More capabilities will be added only after their
     * legacy behaviour has been traced and verified.
     */
    public function getCapabilitiesForUser(int $userId): array
    {
        return [
            'items.view'    => $this->can($userId, 'items.view'),
            'items.add'     => $this->can($userId, 'items.add'),
            'items.edit'    => $this->can($userId, 'items.edit'),
            'items.archive' => $this->can($userId, 'items.archive'),
        ];
    }

    /**
     * Load the minimum identity required for authorization.
     *
     * Only an active YouAudit user can receive UAsset capabilities.
     */
    private function getActiveYouAuditUser(int $userId): ?array
    {
        $user = $this->db->table('users')
            ->select([
                'id',
                'level_id',
                'active',
                'youaudit',
            ])
            ->where('id', $userId)
            ->where('active', 1)
            ->where('youaudit', 1)
            ->get()
            ->getRowArray();

        return $user ?: null;
    }

    /**
     * ITEMS - VIEW
     *
     * Legacy web behaviour does not use an Items.view permission.
     *
     * Active YouAudit users in legacy levels 1-7 may enter the
     * item-view flow.
     *
     * Access to an individual item must still be scoped to an
     * account the authenticated user is authorised to access.
     *
     * Legacy single-account behaviour scopes the item using:
     * item ID + the authenticated user's account ID.
     *
     * Account/item scoping must be enforced by the item endpoint/data
     * layer and must not rely on this UI capability alone.
     */
    private function canViewItems(int $userId, int $levelId): bool
    {
        return $levelId >= 1 && $levelId <= 7;
    }

    /**
     * ITEMS - ADD
     *
     * Levels 1-4:
     * Use the verified legacy Items.addOne permission.
     *
     * Level 5 AppOnly:
     * Legacy mobile dashboard exposes Add New / Add Similar and the
     * V5 itemAdd endpoint accepts an authenticated app session.
     *
     * Level 6 AppOnly Lite:
     * Legacy mobile dashboard uses its restricted five-option menu
     * and does not expose Add New / Add Similar.
     *
     * Level 7 AppOnly Auditor:
     * Legacy mobile dashboard follows the normal branch and exposes
     * Add New / Add Similar. The V5 itemAdd endpoint has no additional
     * level or Items.addOne permission check.
     */
    private function canAddItems(int $userId, int $levelId): bool
    {
        if ($levelId >= 1 && $levelId <= 4) {
            return $this->hasLegacyPermission(
                $userId,
                'Items.addOne'
            );
        }

        if ($levelId === 5) {
            return true;
        }

        if ($levelId === 6) {
            return false;
        }

        if ($levelId === 7) {
            return true;
        }

        return false;
    }

    /**
     * ITEMS - EDIT
     *
     * Levels 1-4:
     * Use the verified legacy Items.editOne permission.
     *
     * Level 5 AppOnly:
     * Existing iOS Item screen permits Edit because its legacy
     * condition allows levels greater than 1 and less than 7.
     *
     * Level 6 AppOnly Lite:
     * Existing iOS Item screen does not expose normal Edit.
     *
     * Level 7 AppOnly Auditor:
     * Existing iOS Item screen does not expose normal Edit.
     */
    private function canEditItems(int $userId, int $levelId): bool
    {
        if ($levelId >= 1 && $levelId <= 4) {
            return $this->hasLegacyPermission(
                $userId,
                'Items.editOne'
            );
        }

        if ($levelId === 5) {
            return true;
        }

        return false;
    }

    /**
     * ITEMS - ARCHIVE
     *
     * Levels 1-4:
     * Use the verified legacy Items.markDeleted permission.
     *
     * Level 5 AppOnly:
     * Legacy mobile dashboard explicitly removes Archive/Remove Items.
     *
     * Level 6 AppOnly Lite:
     * Restricted dashboard does not expose Archive/Remove Items.
     *
     * Level 7 AppOnly Auditor:
     * Kept denied for now.
     *
     * Although the legacy normal dashboard appears to expose Archive
     * for level 7, V5 archiveItem calculates Items.markDeleted but its
     * permission guard is commented out. That legacy security weakness
     * is not carried into V1 without confirming the intended business
     * rule.
     */
    private function canArchiveItems(int $userId, int $levelId): bool
    {
        if ($levelId >= 1 && $levelId <= 4) {
            return $this->hasLegacyPermission(
                $userId,
                'Items.markDeleted'
            );
        }

        return false;
    }
}
