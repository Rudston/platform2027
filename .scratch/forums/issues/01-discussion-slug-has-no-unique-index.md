# 01: A discussion slug is checked in PHP but nothing enforces it

**What to build:** `forum_discussions.slug` needs the constraint its uniqueness
check assumes — or the check needs to stop implying one.

`ForumService::discussionSlugExists()` asks whether a slug is already used among
a group's discussions, and `ForumDiscussionModal` refuses to save on a hit. But
`2026_07_16_000003_create_forum_discussions_table.php` declares only

```php
$table->string('slug')->nullable();
```

with **no index at all** — not unique, not even plain. Verified against the live
MySQL schema: `forum_groups` and `poll_groups` both carry
`(circle_id, slug) UNIQUE`; `forum_discussions` carries nothing.

Two consequences:

1. **The check is racy.** Two people creating a discussion with the same title in
   the same group at the same time both pass `discussionSlugExists()` and both
   insert. `communities.forums.discussions.show` binds by slug with
   `scopeBindings()`, so from then on one of the two discussions is unreachable —
   whichever the query does not return first. No error, no clue in the UI.
2. **The lookup is unindexed.** Every discussion page load resolves
   `where slug = ?` by scan. Harmless at today's row counts, and it will not stay
   that way.

The group tables are the precedent: the same PHP-level check sits on top of a
real `(circle_id, slug)` unique index, so a race loses at the database instead of
corrupting the route. The parallel index here would be
`(forum_group_id, slug)` — scoped to the group, matching what
`discussionSlugExists()` actually queries.

Decide while in there whether the column should also become NOT NULL. Note the
group columns were deliberately LEFT nullable (`.scratch/polls/issues/13`) on the
grounds that nothing can write null and a migration would guard an unreachable
state — the same argument applies here, so probably index only.

Existing data is clean: 0 duplicate `(forum_group_id, slug)` pairs and 0 empty
slugs at the time of filing, so the migration should apply without a backfill —
re-check before writing it.

**Found by:** verifying the real MySQL schema after
`.scratch/polls/issues/13`, which consolidated the slug helpers but did not
change any migration.

**Blocked by:** None.

**Status:** needs-triage

- [ ] `(forum_group_id, slug)` unique index on `forum_discussions`
- [ ] Duplicate/empty slugs re-checked before the migration runs
- [ ] The NOT NULL question decided explicitly, consistent with issue 13
