# 07: Extract the shared test schema builder

**What to build:** A test can declare the tables it needs in one line instead of
hand-rolling them. Five poll test files currently repeat 43–67 lines of the same
`Schema::create('circles', …)` plus membership and poll migrations, and
seventeen older tests do the same thing again.

This is PREFACTOR: every other ticket in this set adds tests, and without it
each one copies the block a sixth, seventh and eighth time. Make the change
easy, then make the easy change.

Note why the duplication exists: CLAUDE.md forbids `RefreshDatabase` because the
full migration set fails on sqlite (a demography backfill references a
`countries` table no migration creates). The concern must preserve that — build
only what a test asks for, never everything.

**Blocked by:** None (can start immediately).

**Status:** resolved

- [x] A test opts into circles, memberships, tagging and the poll tables individually
- [x] Poll migrations are matched by name, not by date, so a later one is picked up
- [x] The five poll test files use it and their setUp shrinks accordingly
- [x] Full suite green, with no test relying on tables it did not ask for
- [x] `RefreshDatabase` still unused

## Answer

`tests/Support/TestSchema.php` — a fluent, immediately-applying builder:

```php
TestSchema::make()->permissions()->memberships()->tagging()->polls();
```

- Each opt-in (`users`, `permissions`, `circles`, `memberships`, `tagging`,
  `polls`, `pollRatingScales`) applies at once, returns `$this`, and is a no-op
  if that builder already did it, so overlapping asks (`polls()` already covers
  the rating scales) never collide with "table already exists". The bookkeeping
  is per-INSTANCE — one builder per test — except for the hand-built tables,
  which check the schema itself, so a test still hand-rolling its own `circles`
  can adopt an opt-in that needs one. That is the migration path for the older
  tests.
- Prerequisites are pulled in where a foreign key needs them (`memberships()`
  and `polls()` imply `circles()` + `users()`), so a test cannot half-declare.
- Nothing else is built. Tables a test did not ask for stay absent, which keeps
  the `RefreshDatabase` prohibition intact rather than routing around it.
- `polls()` globs `*_poll*.php` and `pollRatingScales()` globs
  `*_poll_rating_scale*.php` — matched by NAME, so a later poll migration is
  picked up; `glob()` sorts, and date-prefixed names sort into run order, so
  creates precede their ALTERs.
- `circles` and `themes` stay hand-rolled: the real circles chain declares its
  morphs NOT NULL (tests insert bare circles via the query builder on purpose,
  so `Circle::booted()` does not fire), and the `themes` migration wrote
  `label` where the model reads `name`.

`tests/Feature/TestSchemaTest.php` pins the behaviour, including that opting
into one thing builds nothing else and that the name-matching is complete.

Adopted by the six poll test files. Each setUp's schema block became one
`TestSchema::make()` call: 24–36 lines gone per file (3 from the rating-scale
seeder test, which only globbed), 2,390 → 2,237 lines across the six. The ~18
older non-poll tests still hand-roll their own blocks — retiring those is
follow-up work this ticket did not claim. Full suite: 293 passed.
