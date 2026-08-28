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

**Status:** resolved

- [x] `(forum_group_id, slug)` unique index on `forum_discussions`
- [x] Duplicate/empty slugs re-checked before the migration runs
- [x] The NOT NULL question decided explicitly, consistent with issue 13

## Answer

`2026_08_28_000001_add_unique_slug_index_to_forum_discussions_table` adds
`unique(['forum_group_id', 'slug'])` — scoped to the GROUP, matching what
`discussionSlugExists()` actually queries and mirroring `forum_groups`'
`(circle_id, slug)`. Two groups may each run a discussion called "Welcome"; one
group may not run two.

**The column stays NULLABLE**, per the decision recorded for the group tables in
`.scratch/polls/issues/13`: every write goes through `requireSlugFor()`, which
throws rather than store an empty or absent slug, so the app cannot produce null
and a NOT NULL migration would guard a state nothing reaches. MySQL does not
compare NULLs inside a unique index, so nullable and unique coexist without a
special case.

### Soft deletes — why deleted_at is NOT in the index

`forum_discussions` soft-deletes, and this index deliberately omits
`deleted_at`, so a trashed discussion keeps holding its slug and that title
cannot be reused until it is force-deleted.

Adding `deleted_at` to the index looks like the fix and is the opposite of one:
every LIVE row has `deleted_at = NULL`, and MySQL treats any tuple containing a
NULL as never equal to another, so a three-column index would silently accept
unlimited duplicates among exactly the rows the constraint exists to protect.
The two-column index is also what `forum_groups` already does, so the two tables
behave the same way.

### Verification

Pre-flight on the live database before writing the migration: 2 discussions, 0
trashed, 0 duplicate `(forum_group_id, slug)` pairs including trashed rows, 0
null slugs — so no backfill was needed. After migrating, `SHOW INDEX` reports
`forum_discussions_forum_group_id_slug_unique` over both columns, `slug` is still
`null=YES`, and a deliberate duplicate insert is refused with SQLSTATE 23000
(1062).

Note the migration was applied with `--path`, NOT a bare `artisan migrate`:
`2026_08_27_000001_drop_hide_voter_identities_from_polls_table` was also pending
on this machine, and dropping that column was not this ticket's business.
`polls.hide_voter_identities` therefore still exists in the dev database even
though ADR-0004 retired it in code.

`tests/Feature/ForumDiscussionsTest::test_a_duplicate_discussion_slug_is_refused_by_the_database`
pins it, using the query builder on purpose so it exercises the CONSTRAINT
rather than the check in front of it: the same slug in another group inserts
fine, the same slug in the same group throws `QueryException`. Verified to fail
when the index migration is left out of the test's setUp.

The migration was added to the setUp of all SEVEN test files that hand-roll
`forum_discussions` (ForumDiscussionsTest, ForumGroupsTest, ThemeTaggingTest,
CommentsLikesTest, CommentModerationTest, CommentModerationPageTest,
CircleOversightTest) rather than only the one that needs it — otherwise a test
could pass against a schema the application does not have. This is the
duplication `.scratch/polls/issues/07` set out to retire; those seven are among
the ~18 files still hand-rolling their own blocks.

Full suite: 336 passed, 1182 assertions.