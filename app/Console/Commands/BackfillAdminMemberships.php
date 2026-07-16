<?php

namespace App\Console\Commands;

use App\Enums\CommunityType;
use App\Models\Circles\Circle;
use App\Models\Circles\CircleMembership;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Give every existing circle_admin an active circle membership. circle_admin
 * was granted before the membership system existed, so those admins have no
 * circle_memberships row yet. Admins of an organisation community get the
 * 'organisation_member' internal role. Idempotent, adds-only — safe to re-run.
 * Manual/occasional maintenance; NOT scheduled.
 *
 * ONE-OFF MIGRATION AID: this should not be needed going forward. New
 * circle_admins now receive a membership automatically at approval time
 * (RequestController::approve() and RequestResource::approveAction()). Once the
 * pre-membership-system data has been backfilled, this command is only useful
 * if legacy circle_admins are created by some other path that skips that grant.
 */
class BackfillAdminMemberships extends Command
{
    protected $signature = 'circles:backfill-admin-memberships';

    protected $description = 'Give every existing circle_admin an active circle membership (idempotent, safe to re-run).';

    public function handle(): int
    {
        $columns = (array) config('permission.column_names');
        $tables = (array) config('permission.table_names');

        $modelHasRoles = $tables['model_has_roles'] ?? 'model_has_roles';
        $rolesTable = $tables['roles'] ?? 'roles';
        $modelKey = $columns['model_morph_key'] ?? 'model_id';
        $teamKey = $columns['team_foreign_key'] ?? 'circle_id';

        $created = 0;

        // Every circle-scoped circle_admin assignment (user + circle).
        DB::table($modelHasRoles)
            ->join($rolesTable, "{$rolesTable}.id", '=', "{$modelHasRoles}.role_id")
            ->where("{$rolesTable}.name", 'circle_admin')
            ->where("{$modelHasRoles}.model_type", (new User)->getMorphClass())
            ->whereNotNull("{$modelHasRoles}.{$teamKey}")
            ->select([
                "{$modelHasRoles}.{$modelKey} as user_id",
                "{$modelHasRoles}.{$teamKey} as circle_id",
            ])
            ->orderBy("{$modelHasRoles}.{$teamKey}")
            ->chunk(200, function ($rows) use (&$created): void {
                foreach ($rows as $row) {
                    // Skip if the user already has an active membership here.
                    $alreadyMember = CircleMembership::query()
                        ->where('circle_id', $row->circle_id)
                        ->where('user_id', $row->user_id)
                        ->whereNull('left_at')
                        ->exists();

                    if ($alreadyMember) {
                        continue;
                    }

                    $circle = Circle::find($row->circle_id);

                    if (! $circle) {
                        continue;
                    }

                    $internalRole = $circle->circleable_type === CommunityType::Organisation->value
                        ? 'organisation_member'
                        : null;

                    CircleMembership::create([
                        'circle_id' => $row->circle_id,
                        'user_id' => $row->user_id,
                        'internal_role' => $internalRole,
                        'joined_at' => now(),
                        // Admins are trusted, so the role is approved outright —
                        // required for hasApprovedInternalRole() / the members list.
                        'metadata' => $internalRole !== null ? ['internal_role_approved' => 'approved'] : null,
                    ]);

                    $created++;
                }
            });

        $this->info("{$created} admin memberships backfilled");

        return self::SUCCESS;
    }
}
