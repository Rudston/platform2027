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

**Status:** ready-for-agent

- [ ] A non-member opening a running Poll gets a 404, as a Draft already does
- [ ] A group page does not list Polls the viewer may not open
- [ ] Members and circle managers are unaffected
- [ ] A Closed Poll whose Result is published stays reachable to a non-member
- [ ] A Closed Poll whose Result is NOT published is member-only
- [ ] Tests cover visitor, member and manager against open, closed-published and closed-unpublished
