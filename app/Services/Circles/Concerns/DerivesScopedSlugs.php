<?php

namespace App\Services\Circles\Concerns;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Deriving a slug, and asking whether one is already taken among its siblings.
 *
 * Both the forums and polls services need the identical rule, so it lives here
 * once: slugs are unique per CIRCLE rather than globally (two Circles may each
 * run a forum group called News — the unique index is (circle_id, slug)), and a
 * record being EDITED must not report its own slug as taken.
 *
 * The services keep their own public method names, because those read in the
 * language of what they own (groupSlugTaken, discussionSlugExists); what they
 * share is the mechanism, not the vocabulary.
 */
trait DerivesScopedSlugs
{
    /**
     * The slug a name yields — the ONE definition for both services.
     *
     * MAY RETURN AN EMPTY STRING, and every caller must reject that rather than
     * store it. Str::slug transliterates to ASCII and legitimately comes back
     * empty for a name written in a non-Latin script ('中文名字') or made only
     * of punctuation ('???'); since every group and discussion route binds by
     * slug, an empty one is unroutable and building the link throws
     * UrlGenerationException, taking the whole service tab down with it.
     *
     * This is the ASKING half, so it answers rather than throwing: the compose
     * forms use it to pre-check, and slugTaken/groupSlugTaken to look a name up.
     * Writes use requireSlugFor() below, which refuses an empty one.
     *
     * The user-facing rejection belongs in the compose form, where the person
     * can fix it by supplying a name or an explicit URL slug — see
     * ForumGroupModal, ForumDiscussionModal and PollGroupModal, which all say
     * so in words. A generated fallback was considered and rejected: it would
     * hand someone a meaningless URL they never chose, and silence a message
     * that already existed.
     */
    public function slugFor(string $name): string
    {
        return Str::slug($name);
    }

    /**
     * The slug to STORE — derived, or an exception. Every write goes through
     * here, never slugFor() directly.
     *
     * slugFor() may legitimately return nothing (see above), and a record
     * stored with an empty slug has an unroutable page that throws while
     * rendering the whole service tab. The compose forms pre-check and answer
     * with a friendly translated message, so no person reaches this; it is
     * there so a future caller that is NOT a compose form — a seeder, an
     * importer, a Filament action — fails loudly at the insert instead of
     * quietly creating the broken record. The belt to the forms' braces.
     */
    protected function requireSlugFor(string $name): string
    {
        $slug = $this->slugFor($name);

        if ($slug === '') {
            throw new InvalidArgumentException(
                "[{$name}] yields no usable slug — ask for a name or an explicit slug.",
            );
        }

        return $slug;
    }

    /**
     * Is $slug already used by one of $siblings — a circle's groups, or a
     * group's discussions — ignoring one of them?
     *
     * $ignoreId is the record being edited: without it, saving a group whose
     * name has not changed would collide with itself.
     */
    protected function slugExistsAmong(Relation $siblings, string $slug, ?int $ignoreId = null): bool
    {
        return $siblings
            ->where('slug', $slug)
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();
    }
}
