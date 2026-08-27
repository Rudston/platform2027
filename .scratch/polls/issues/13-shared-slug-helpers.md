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

**Status:** ready-for-agent

- [ ] One shared concern, used by both services
- [ ] Slug uniqueness stays scoped to the Circle
- [ ] Editing an existing group still ignores its own slug when checking
- [ ] Existing poll and forum slug tests pass unchanged
