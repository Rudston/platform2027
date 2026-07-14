<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Requests\RequestResource;
use Filament\Pages\Dashboard as BaseDashboard;

/**
 * The panel dashboard. circle_admins can reach the panel (see
 * User::canAccessPanel) but should only see the Requests resource.
 *
 * The dashboard IS the panel's home URL (/admin), so it must remain accessible
 * — denying canAccess() would 403 the home route rather than redirect. Instead
 * we keep access open, hide it from the circle_admin nav, and bounce them to
 * the Requests list on arrival.
 */
class Dashboard extends BaseDashboard
{
    public static function canAccess(): bool
    {
        // Open, so /admin never 403s; non-admins are redirected in mount().
        return true;
    }

    /** Only global admins/superadmins see the dashboard in the navigation. */
    public static function shouldRegisterNavigation(): bool
    {
        return static::isPrivileged();
    }

    public function mount(): void
    {
        if (! static::isPrivileged()) {
            $this->redirect(RequestResource::getUrl('index'));
        }
    }

    private static function isPrivileged(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();

        return (bool) $user?->hasAnyRole(['admin', 'superadmin']);
    }
}
