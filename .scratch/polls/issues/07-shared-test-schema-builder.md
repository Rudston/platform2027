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

**Status:** ready-for-agent

- [ ] A test opts into circles, memberships, tagging and the poll tables individually
- [ ] Poll migrations are matched by name, not by date, so a later one is picked up
- [ ] The five poll test files use it and their setUp shrinks accordingly
- [ ] Full suite green, with no test relying on tables it did not ask for
- [ ] `RefreshDatabase` still unused
