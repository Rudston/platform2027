# 13: One home for circle-scoped slug helpers

**What to build:** The rule for deriving a slug and checking whether it is taken
within a Circle lives in one place instead of two. The polls and forums services
each carry their own copy, line for line, including the ignore-this-id case used
when editing.

Behaviour must not change: slugs stay unique per Circle, not globally, and both
services keep their existing friendly collision errors.

Worth noting while in there: a null slug makes a group page unreachable and
throws while rendering the whole tab, because both routes bind by slug. The
services always derive one, so it cannot happen through the app — but the column
is nullable in both. Decide whether that is worth tightening or leaving.

**Blocked by:** 07 (shared test schema builder).

**Status:** resolved

- [x] One shared concern, used by both services
- [x] Slug uniqueness stays scoped to the Circle
- [x] Editing an existing group still ignores its own slug when checking
- [x] Existing poll and forum slug tests pass unchanged

## Answer

`App\Services\Circles\Concerns\DerivesScopedSlugs` is the one home. `ForumService`
and `VotingService` both `use` it and keep their own public vocabulary —
`slugTaken`/`slugExists`/`discussionSlugExists` on one, `groupSlugTaken`/
`groupSlugExists` on the other — because those names read in the language of what
each service owns. What moved is the mechanism, not the words:

- `slugFor(string $name): string` — the single derivation.
- `slugExistsAmong(Relation $siblings, string $slug, ?int $ignoreId)` — the
  uniqueness check. Scoped to the passed relation, so it is per CIRCLE (or per
  group, for discussions) and never global; `$ignoreId` is the record being
  edited. Both services' collision methods are now one line each, and their
  friendly error messages are untouched.

Behaviour is unchanged. `VotingService` previously wrote the same query inline
and `ForumService` carried the identical trait-shaped copy.

### The nullable-slug question the ticket raised

Left NULLABLE, deliberately, and the reasoning is now recorded in CLAUDE.md and
next to `VotingService::updateGroup`. Every write path goes through
`ForumGroupModal`, `ForumDiscussionModal` or `PollGroupModal`, and each derives a
slug or refuses to save — so the app cannot write null, and a NOT NULL migration
across two tables would guard a state nothing produces.

The reachable hazard was not null but the EMPTY string. `Str::slug`
transliterates to ASCII and legitimately returns `''` for a name in a non-Latin
script (`中文名字`) or one made only of punctuation (`???`); every group route binds
by slug, so an empty one is unroutable and building the link throws
`UrlGenerationException`, taking the whole service tab down.

A generated fallback (`g-` plus a hash of the name) was implemented first and
then REVERTED: it hands someone a meaningless URL they never chose, and silences
a message `ForumGroupModal` and `ForumDiscussionModal` already had. So `slugFor`
may return `''` — its docblock says so in capitals — and the rejection lives in
the compose form, where the person can fix it by supplying a usable name or
typing the URL slug themselves. `PollGroupModal` was the one form missing that
guard; it now adds a `slug` error (`polls.group.slug_required`, added to
`lang/en` and `lang/pt`) and returns, matching the forum modals. The poll group
modal view already rendered `@error('slug')`, so the message is visible.

`lang/pt` gets the English string, like every other key in that file's `group`
section — translating polls into Portuguese is issue 06, still `ready-for-human`.

### From the code review

`/code-review` found no correctness defect in the extraction. Three low findings;
two fixed here, one filed elsewhere.

**The contract had no teeth (fixed).** The trait's docblock asserted "every
caller must reject an empty slug", but enforcement lived in three Livewire
modals while all five write paths assigned `slugFor(...)` straight into the
insert. Nothing produces that state today, so it was latent — but it is a
one-line invariant sitting outside the layer CLAUDE.md calls the single write
entry point, and a future seeder or Filament action would have re-opened exactly
the hole this ticket set out to close. `requireSlugFor()` now derives or throws
`InvalidArgumentException`, and every write uses it: `createGroup`/`updateGroup`
on both services, plus `createDiscussion`. `slugFor()` stays the asking half —
the forms' pre-check and the `*SlugTaken` lookups must answer, not throw. This
is the belt-and-braces shape used elsewhere in the codebase (compare
`PollResponse::isChoiceVisibleTo`), and it means nobody ever sees the exception.

**A slug clash was reported on the wrong field (fixed).** `PollGroupModal::save()`
put the new empty-slug error on `slug` but left the collision error on `name`.
Type an explicit URL slug that collides and the message "A group with a similar
NAME already exists" renders under the Name input, whose value is unique and
irrelevant — you rename the group and it still will not save. The error now
attaches to the field actually typed: `slug` when one was supplied, else `name`.
(Slightly more precise than `ForumGroupModal`, which always uses `slug`.)

**A live bug outside this ticket (filed, not fixed).**
`ThemeSuggestion::approve()` dedupes Themes with a bare `Str::slug($this->name)`
against a UNIQUE `themes.slug`, so every unslugabble tag suggestion collapses
onto whichever was approved first — the second suggester silently gets somebody
else's tag attached to their content, plus an "approved" email. Different
subsystem, and the fix needs a decision about where the rejection belongs, so it
is filed as `.scratch/tagging/issues/01` (`needs-triage`) rather than smuggled in
here.

Both fixes are covered: a service-level refusal test per service (create derived,
create with an explicit slug, update, and discussion create), each verified to
fail with the guard disabled.

### Tests

- `VotingServiceTest` — circle-scoped uniqueness, the edit-ignores-itself case,
  and that `slugFor` yields nothing for the four empty-slug names while
  `route(...)` with an empty slug throws, which is why callers must reject it.
- `ForumGroupsTest` — the same two service-level cases, plus the modal refusing
  an unslugabble name and the explicit-slug escape hatch.
- `PollModalTest` — the new `PollGroupModal` guard: refused with a `slug` error,
  nothing written, and the escape hatch saving as `budget-talk`. Verified to bite
  by removing the guard (red) and restoring it (green).

Full suite: 335 passed, 1180 assertions.

Note on the environment: Xdebug 3.2.0RC2 on MAMP's PHP 8.2.0 segfaults partway
through `PollModalTest` (exit 139) at HEAD as well as on this branch — unrelated
to these changes. Run with `-d xdebug.mode=off`.