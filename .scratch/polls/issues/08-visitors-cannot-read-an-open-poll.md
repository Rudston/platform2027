# 08: A visitor cannot read an open Poll

**What to build:** Someone who is not a member of the Circle cannot see a Poll
while it is running. Today a logged-out visitor can open a poll by URL and read
its prompt, options and turnout.

This is US42 — *"As a visitor, I want to be unable to see a Poll while it is
running, so that a Circle's internal deliberation stays internal"* — and the
spec's Out of Scope independently forbids "a publicly viewable LIVE poll".
Q11 settled the shape during design: **member-only while running; only a CLOSED
Poll's Result may be published outside the Circle**, and then only when the
Organiser has said so.

The gate belongs on the POLL, not the Group. ADR-0003 and CONTEXT.md both state
that a Poll Group is organisational only and never gates what is inside it — so
the group's page filters which Polls it LISTS, while the Poll answers for
itself. Do not give Poll Groups a visibility.

**Blocked by:** 07 (shared test schema builder).

**Status:** resolved

- [x] A non-member opening a running Poll gets a 404, as a Draft already does
- [x] A group page does not list Polls the viewer may not open
- [x] Members and circle managers are unaffected
- [x] A Closed Poll whose Result is published stays reachable to a non-member
- [x] A Closed Poll whose Result is NOT published is member-only
- [x] Tests cover visitor, member and manager against open, closed-published and closed-unpublished

## Answer

**`Poll::isReadableBy(CircleViewer $viewer)`** is the single gate:

| state | visitor | member | manager |
| --- | --- | --- | --- |
| Draft | 404 | 404 | ok |
| Scheduled / Open | 404 | ok | ok |
| Closed, Result released | ok | ok | ok |
| Closed, not released | 404 | ok | ok |
| Cancelled | 404 | ok | ok |

404 not 403, as a Draft already did. It gates `PollPage::mount()` and filters
`PollGroupPage::polls()`; the group page itself stays ungated and simply lists
less, per ADR-0003. No visibility was added to Poll Groups.

Three things the checklist did not spell out:

1. **The Roster was leaking.** Making a closed, published poll reachable to a
   non-member (checkbox 4) also exposed the block naming every Respondent, which
   renders for ANY closed poll. `Poll::rosterNamesAreVisibleTo()` now gates it on
   standing in the Circle: a visitor gets totals, winner and turnout — the
   Result, as CONTEXT.md defines it — never who responded. Q11 releases the
   Result, and the Roster is not part of it.
2. **`resultIsPublic()` was the wrong gate**, caught in review. It requires a
   FROZEN Result, but a poll that runs out its clock is not frozen until someone
   reads it or the hourly command runs (ADR-0001) — and `PollPage` freezes AFTER
   the gate, so a visitor could never heal it: a published Result would 404 for
   up to an hour. Split into `resultIsReleased()` (Closed, not Cancelled,
   `publish_results`) for the gate and `resultIsPublic()` = released AND frozen
   for rendering, the second composed from the first so they cannot drift.
   Pinned by `test_a_result_published_on_a_clock_closed_poll_is_readable_before_it_is_frozen`,
   which fails against the old gate.
3. **A departed member now gets a 404** where they used to read
   `polls.respond.left_circle` ("You are no longer a member…"). That follows from
   checkbox 1 — a non-member is a visitor — so it is implemented as specified,
   but the message is now reachable only by a manager who is in the electorate
   and not a member. The branch and lang key were left in place.

**`App\Support\Circles\CircleViewer`** carries the viewer's standing in one
circle (`membership`, `managesCircle`), resolved once per request. The gate takes
it rather than a `?User` for two reasons: the listing filters N polls with no
query per row (pinned by a test asserting the predicate issues no queries), and
because the object DERIVES both facts from the Circle and the User, a caller
cannot hand the gate authority it made up.

`tests/Feature/PollVisibilityTest.php` — 10 tests, 65 assertions: one state
matrix driving both the predicate and an HTTP sweep through the real routes, plus
the group listing, the roster, and the clock-closed regression. Full suite: 304
passed.

**Still open (not asked for by any checkbox):** the Polls tab
(`PollServiceContainer`) still shows a visitor `totalPolls`, `openPolls` and each
group's `polls_count`, so poll EXISTENCE and counts leak even though no content
does. Filtering `withCount('polls')` by readability needs a query design, not a
one-liner — worth its own ticket.
