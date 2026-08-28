<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Give forum_discussions.slug the constraint its uniqueness check assumes.
 *
 * ForumService::discussionSlugExists() asks whether a slug is taken among a
 * group's discussions and ForumDiscussionModal refuses to save on a hit, but
 * the table carried no index at all — so two people posting the same title in
 * one group at the same moment both passed the check and both inserted. The
 * discussion route binds by slug (scopeBindings), so one of the two then became
 * permanently unreachable, silently.
 *
 * Scoped to the GROUP, matching what discussionSlugExists() queries and
 * mirroring forum_groups' (circle_id, slug) — two groups may each run a
 * discussion called "Welcome".
 *
 * The column STAYS NULLABLE, consistent with the decision taken for
 * forum_groups.slug and poll_groups.slug (.scratch/polls/issues/13): every
 * write goes through requireSlugFor(), so the app cannot store null, and a NOT
 * NULL migration would guard a state nothing produces. MySQL does not compare
 * NULLs in a unique index, so nullable and unique coexist here.
 *
 * NOTE — soft deletes: forum_discussions softDeletes, and this index does NOT
 * include deleted_at, so a trashed discussion keeps holding its slug and the
 * title cannot be reused until it is force-deleted. That is deliberate: it
 * matches forum_groups exactly, and it keeps a restored discussion's URL valid.
 * Adding deleted_at to the index would NOT be the fix — every live row has
 * deleted_at NULL, and MySQL treats NULL-bearing tuples as never equal, which
 * would silently disable the constraint for exactly the rows it must protect.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forum_discussions', function (Blueprint $table) {
            $table->unique(['forum_group_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::table('forum_discussions', function (Blueprint $table) {
            $table->dropUnique(['forum_group_id', 'slug']);
        });
    }
};
