# Polls service

Status: implemented; amended after review — see **Amendments** at the end.

The body below is the spec AS WRITTEN BEFORE the code, and is deliberately left
that way: it is the baseline a spec review is measured against, and editing the
stories to match what was built would make that review meaningless. Decisions
taken after the review are recorded as amendments instead, so both the original
intent and the outcome survive.

Authoritative background, which this spec does not restate:
`POLLING_SERVICE.md` (schema), `CONTEXT.md` (vocabulary — every capitalised
domain term below is defined there), and `docs/adr/0001`–`0003`.

## Problem Statement

A Circle has no way to make a decision together. Members can discuss a question
in a forum, but there is no way to put it to the community and get an answer
that anyone can trust.

Everything a Circle currently decides therefore happens somewhere the platform
cannot see: in a WhatsApp group, at a meeting, or in a circle admin's head. The
consequences show up as three distinct failures.

First, there is no way to *hold* an election. Circle admins are appointed by
platform admins or granted the role on organisation approval. A community that
wants to choose its own steward cannot.

Second, there is no way to consult members at scale. A ward committee wanting to
know which of four road-repair options residents prefer has to count replies in
a forum thread by hand, with no idea who was entitled to reply or how many
didn't.

Third, and worst, nothing decided this way can be *checked*. A member told "the
community chose option B" has no way to see how many people were asked, how many
answered, or whether the count is right. In a contested decision — which is
exactly when it matters — the platform offers nothing but an assertion.

## Solution

A Polls service, attachable to any Circle, that runs a structured collective
decision from composition to frozen Result.

An Organiser writes a Poll inside a Poll Group, chooses who is eligible and how
responses will be counted, and publishes it. Eligible members respond during its
window. When it Closes, the Result freezes: per-option totals, turnout, and the
winning option.

The design goal that shapes everything else is **verifiability without
surveillance**. No one — not the Organiser, not a platform admin, not a
superadmin — can see how an individual voted. Yet any member can confirm the
Result is honest:

- the Electorate was fixed when the Poll was published, so the denominator
  cannot move;
- the Roster shows a live count while the Poll runs and the names of everyone
  who responded once it Closes, so a member finds their own name on it;
- the Result freezes at Close, so it is a fixed claim rather than a computation
  that might answer differently next year.

A member can therefore open a concluded election and confirm "47 of 62
responded", see their own name among the 47, and add the per-option totals up to
47 — while learning nobody's choice, including their own neighbour's.

## User Stories

**Organising**

1. As a circle admin, I want to create a Poll Group with a name and description, so that related Polls are presented together as one effort rather than scattered in a list.
2. As a circle admin, I want to name a Poll Group inline while writing my first Poll, so that I am not forced through a separate setup step before I can ask a question.
3. As a circle admin, I want to archive a Poll Group I no longer run, so that the sidebar stays short without losing anything.
4. As a circle member, I want archived Groups' Polls to remain findable, so that a decision the community made two years ago can still be looked up.
5. As a circle admin, I want to order Poll Groups, so that the active effort appears above dormant ones.
6. As a circle admin, I want to tag a Poll with a Theme, so that members browsing "Water" find it alongside water-related content elsewhere on the platform.
7. As a circle member, I want to see a Poll's tags, so that I can tell at a glance what it concerns.

**Composing a Poll**

8. As a circle admin, I want to compose a Poll as a Draft, so that I can work on the wording over several sittings before anyone sees it.
9. As an Organiser, I want to give a Poll a title, a description and a Prompt, so that a Respondent understands both the context and the specific instruction.
10. As an Organiser, I want to add, reorder, edit and remove options while the Poll is a Draft, so that I can get the ballot right before it matters.
11. As an Organiser, I want to choose the Response Shape — pick one, rank several, or score several — so that the ballot matches the kind of question I am asking.
12. As an Organiser, I want to be offered only the Tally Methods legal for my chosen Response Shape, so that I cannot accidentally configure a poll that averages a single-choice ballot.
13. As an Organiser, I want to require a full ranking on a ranked-choice Poll, so that no partial rankings complicate the count.
14. As an Organiser, I want to pick a Rating Scale from a curated list for a rating Poll, so that my results are comparable with other Circles' and I do not have to invent one.
15. As an Organiser, I want to set when a Poll opens and closes, so that it runs on a schedule I do not have to action manually.
16. As an Organiser, I want to publish a Draft, so that eligible members can begin responding.
17. As an Organiser, I want to un-publish a Poll that has no responses yet, so that a typo spotted an hour after publishing can be fixed rather than voided.
18. As an Organiser, I want to be prevented from editing a published Poll's options once anyone has responded, so that nobody is recorded as having voted on a ballot they never saw.

**Eligibility and the Electorate**

19. As an Organiser, I want to restrict a Poll to members of my Circle, so that outsiders cannot influence a community decision.
20. As an Organiser, I want to restrict a Poll to members holding an approved internal role, so that a decision reserved to an organisation's own people stays with them.
21. As an Organiser, I want to set a Qualifying Date before a Poll was announced, so that people who join specifically in order to vote cannot.
22. As a circle member, I want my vote to survive my later leaving the Circle, so that a decision I lawfully took part in is not annulled after the fact.
23. As a circle member who joined after the Qualifying Date, I want to be told clearly that I am not eligible and why, so that I do not think the platform is broken.
24. As a circle member who has left the Circle, I want to be unable to respond even though I am in the Electorate, so that ex-members cannot vote in a community they left.
25. As a circle member, I want the number of eligible members to stay fixed while a Poll runs, so that a turnout figure means something.

**Responding**

26. As an eligible member, I want to see the Polls I can respond to in my Circle, so that I do not miss one.
27. As an eligible member, I want to pick one option on a single-choice Poll, so that I can register my preference simply.
28. As an eligible member, I want to rank options in order on a ranked-choice Poll, so that my second preference counts if my first is eliminated.
29. As an eligible member, I want to score every option on a rating Poll, so that I can express degrees of support rather than picking one.
30. As an eligible member, I want to be shown my own response after submitting, so that I can confirm what I recorded.
31. As an eligible member, I want to change my response before the Poll closes when the Organiser has allowed it, so that I can reconsider.
32. As an eligible member, I want to be prevented from responding twice, so that the count cannot be stuffed.
33. As an eligible member, I want to be prevented from responding after a Poll closes, so that late responses cannot alter a settled decision.
34. As a Respondent, I want my identity never shown against my choice, so that I can vote honestly about my neighbours.
35. As a Respondent, I want that guarantee to hold against the Organiser and platform staff too, so that it is a real guarantee and not a courtesy.

**Watching and verifying**

36. As a circle member, I want to see how many people have responded while a Poll runs, so that I know whether turnout is worth chasing.
37. As a circle member, I want the list of who has responded to stay hidden until the Poll closes, so that a live Poll is not a list of who has yet to comply.
38. As a circle member, I want to see the Roster of names once a Poll closes, so that I can find my own name and confirm I was counted.
39. As a circle member, I want to see per-option totals and turnout in a Result, so that I can add the numbers up myself.
40. As a circle member, I want a Result to stay exactly as it was when the Poll closed, so that last year's election cannot silently report a different winner.
41. As a circle admin, I want to publish a closed Poll's Result outside the Circle, so that the wider community can see what we decided.
42. As a visitor, I want to be unable to see a Poll while it is running, so that a Circle's internal deliberation stays internal.

**Ending a Poll**

43. As an Organiser, I want to conclude a Poll early once everyone has responded, so that the outcome is available without waiting out the clock.
44. As an Organiser, I want to cancel a Poll that went wrong — a candidate left off the ballot — so that its responses are never counted.
45. As a circle member, I want a cancelled Poll to be visibly void rather than quietly deleted, so that the record shows what happened.
46. As a circle admin, I want to conclude or cancel any Poll in my Circle, so that a Poll is never stuck because its Organiser is unavailable.
47. As an Organiser who has left the Circle, I want to lose the ability to cancel Polls there, so that a departed member cannot void a live election.
48. As a circle member, I want a Poll that simply runs out its clock to close on its own, so that no one has to remember to act.

**Service integration**

49. As a circle member, I want Polls to appear as a tab on the community page alongside Forums, so that it behaves like every other service.
50. As a circle member, I want a link to a Poll to bring me back to the Polls tab, so that navigation is not lost.
51. As a platform admin, I want the Polls service to be attachable to any Circle type, so that campaigns and organisations can use it as well as places.

## Implementation Decisions

**Service handler.** `VotingService` — the existing `CircleServiceContract`
skeleton, already registered by `ServicesSeeder` under `key = 'voting'` — becomes
the real handler and the single write entry point, exactly as `ForumService` is
for forums. It gains group operations (create, update, archive), poll operations
(create, update-draft, publish, conclude, cancel, archive), response operations
(submit, update), and result operations (tally, freeze). The service key stays
`voting` (a stable handle); the display name becomes "Polls".

**Livewire components** follow the per-service grouping convention established
by forums: a container component for the tab plus nested components for the
group list, poll pages and modals, all under a Polls namespace with matching
views. Containers stay thin and delegate to the handler.

**Schema** is specified in `POLLING_SERVICE.md`. The load-bearing decisions:

- **Status records why a Poll stopped, never whether it is open** (ADR-0001).
  Stored: `draft`, `published`, `concluded`, `cancelled`. Derived from the
  clock: Scheduled, Open, Closed. Concluding or cancelling also stamps the close
  time, so status annotates the clock and cannot contradict it. Archiving is a
  nullable timestamp, orthogonal to how the Poll ended.
- **The Electorate is snapshotted at publish, not derived** (ADR-0002), into its
  own table, one row per user, from the append-only membership log as of the
  Qualifying Date. Deriving is wrong for internal-role Polls because the
  approval flag is mutated in place and keeps no history. Casting requires an
  Electorate row **and** current active membership, tested at response time,
  never at tally time — so no response is ever removed retroactively.
- **Every Poll belongs to exactly one Poll Group** (ADR-0003). The FK is NOT
  NULL and restricts on delete; Groups are archived, never deleted; there is no
  default "General" Group.
- **A Poll Group is organisational only** — no visibility, no status. It never
  gates the Polls inside it. Access is answered by the Poll alone, deliberately
  keeping the two-gate matrix (group visibility × poll eligibility) out.
- **Eligibility** is a two-case enum mirroring forum visibility's vocabulary
  (`private`, `internal`) for the same two rules, with no public case. It is the
  same underlying concept as forum participation, kept in its own enum so the
  two can diverge; today they resolve identically. Internal eligibility must be
  checked through the approved-internal-role predicate, never by reading the
  role column alone.
- **Response Shape and Tally Method are separate axes**, with the legal pairings
  declared on the shape — the same single-source-of-truth move as
  `allowedInternalRoles()` on a community type. In v1 the mapping is one-to-one
  (majority-runoff and Borda are deferred); they stay separate because adding a
  method later is then an array edit rather than a schema change and data
  migration.
- **Attribution** is a display rule and is withheld from everyone, the sole
  exception being a user viewing their own response. Voter identity is always
  stored — this is not a secret ballot, and the UI must never call it anonymous.
- **The Roster** is derived from responses, not a table. Its length publishes as
  a live count while the Poll is Open; names appear only at Close.
- **A Result** freezes at Close as JSON on the Poll — per-option totals, turnout,
  winner — plus a frozen-at timestamp. It is a column rather than a table
  because it is small and always read whole, and nothing joins to it. Detail a
  recomputation reproduces exactly, such as an instant-runoff elimination
  sequence, is not stored. Later recomputation checks a Result, never replaces
  it. A Cancelled Poll has no Result.
- **Rating Scales are platform vocabulary**, deliberately without a circle
  reference: curated centrally, seeded (5-point agreement, 1–5 stars, 1–10), and
  picked by Circles, never minted by them.
- **Tagging** reuses the existing `HasTags` trait and polymorphic pivot over
  themes. No new table, and the existing tag-list display component renders them.
- **No stored kind on a Poll.** "Election" and "proposition" are descriptions,
  not data; elections cannot be counted platform-wide. Revisit only if a
  completion action needs to know a Poll was an election.

**Authorization** reuses existing primitives, adding no parallel mechanism.
Managing Groups and creating Polls use the circle-manageability check. Concluding
or cancelling is available to the Organiser *while they remain a member* of the
Circle, and to circle admins unconditionally — the creator-or-manager rule forum
discussions already use, narrowed by a membership test. Circle admins can end a
Poll they cannot read: power over process, none over content.

**Publishing** performs the electorate snapshot in one pass inside a transaction.
The Qualifying Date must not be in the future, so materialisation always happens
at publish and no scheduled job is required.

**Closing** is derived, so a Poll needs no cron to become Closed. Freezing a
Result does need a trigger; it may be written on first read after close, or by
the existing scheduler, but must be idempotent and must never overwrite an
existing frozen Result.

## Testing Decisions

**What makes a good test here.** Tests assert external behaviour — what a caller
observes — not how it is achieved. A test names the rule it protects
(`test_slug_uniqueness_is_scoped_to_the_circle`), not the method it calls. Tests
must not assert on column values that a public predicate already exposes, must
not reach into private state, and must not duplicate a rule they are checking
(a test that recomputes instant-runoff to compare against instant-runoff proves
nothing — expected winners and totals are written out by hand).

**Three seams, confirmed with the developer:**

1. **`VotingService`** — the primary seam. Every state change is driven through
   the handler and its effects asserted through public predicates and relations.
   Covers group CRUD, poll lifecycle, publishing and the electorate snapshot,
   response submission and update, conclude, cancel and archive.
2. **Model predicates on Poll and Poll Group** — `isOpen`, `isClosed`,
   `canRespond`, `hasResponded`, roster and turnout accessors, and the
   conclude/cancel authorization rule. Mirrors how forum visibility and
   participation are tested directly on the model.
3. **Tally as a pure computation** — votes and options in, Result out, with no
   database, Circle or membership. This is where instant-runoff elimination and
   redistribution is tested exhaustively, because constructing circles and
   electorates to check an elimination order is both slow and obscuring.

Livewire components are tested thinly and only for wiring — that a tab renders,
that a create button dispatches the modal event, that a manage-gated modal
returns 403 for a non-manager — following the forum tests.

**Cases that must be covered, because they encode decisions that are easy to
regress:**

- A Poll whose close time has passed reads as Closed while its status is still
  `published` — the ADR-0001 rule, and the most likely thing to be "fixed" wrongly.
- Concluding and cancelling both stamp the close time.
- A Cancelled Poll yields no Result and is never tallied.
- A member who joined after the Qualifying Date is not in the Electorate.
- A member who left after publishing is in the Electorate, keeps a response
  already given, but cannot cast a new one.
- An internal-role Poll's electorate reflects approvals as of publish — the case
  that fails if the electorate is ever derived instead of snapshotted.
- Turnout figures do not change after close.
- Attribution is withheld from the Organiser and from a superadmin, while a user
  can still see their own response.
- Roster names are absent while Open and present once Closed.
- Instant-runoff: majority on first count; elimination and redistribution;
  redistribution past a second eliminated candidate; exhausted ballots under
  partial rankings; a tie.
- A Result already frozen is not overwritten by a later tally.
- An Organiser who has left the Circle cannot cancel; a circle admin still can.

**Prior art.** `ForumGroupsTest` and `ForumDiscussionsTest` are the closest
analogues in shape and should be followed for structure, naming and helpers
(circle creation, granting global and circle-scoped roles). `CircleMembershipTest`
is the reference for membership-rule assertions.

**Constraints.** PHPUnit with namespaced test classes; never `RefreshDatabase` —
the full migration set fails on sqlite, so setup builds only the tables a test
needs by including specific migrations' `up()`. Tests run against sqlite
in-memory with the array mailer. Any fulltext index must be guarded as MySQL-only.
The pure tally seam needs no database at all and should not build one.

## Out of Scope

Deferred by explicit decision during design, not oversight:

- **Majority-runoff and Borda tally methods.** Majority runoff is not a Tally
  Method at all — it spawns a second Poll rather than computing over the first,
  and needs a runoff link, a reduced candidate set and tie policy. Borda is
  single-round and cheap, but nothing currently needs it.
- **Surveys** — multiple questions per instance, free-text responses, branching
  logic, per-question result views. The schema is shaped so these can be added
  without restructuring; the Polls creation UI only ever produces one question.
- **Completion actions** — granting a role to an election winner, marking a
  proposition passed. Only the reserved settings column exists.
- **Secret ballots.** No Poll on this platform is unlinkable; attribution is
  withheld by display rule only.
- **A publicly viewable live Poll.** Only a closed Poll's Result may be published
  outside its Circle.
- **A stored kind on a Poll.**
- **Eligibility beyond the two states** — notably extending to all member Circles
  from a locatable downward. Resolution sits behind one method so this stays a
  one-place change.
- **Notifications.** Nothing emails an eligible member that a Poll has opened or
  is about to close. Email templates are not part of this spec.
- **Poll search.**

## Further Notes

The four worked examples in `POLLING_SERVICE.md` — FPTP election, ranked-choice
election, proposition, rating poll — are the acceptance cases. All four must work
end to end before this is done.

Two pieces of vocabulary already mean something else in this codebase and must
not be reused: **Participant** is a forum contributor derived from having posted,
so poll answerers are **Respondents**; and **Question** is structural only, so
the instruction a Respondent reads is a **Prompt**. "Anonymous" must not appear
in the UI for the same reason.

The riskiest part of the build is not the schema, which is settled, but the
interaction between publishing, the electorate snapshot and the response-time
eligibility check. Those three must be built together; building responses before
the snapshot exists invites eligibility being checked live "temporarily", which
is the exact failure ADR-0002 exists to prevent.

Sequencing this into reviewable stages, given the project's one-step-at-a-time
rule: migrations and enums; then models with their predicates; then the pure
tally computation; then the service handler; then the UI.

---

## Amendments

Recorded 2026-08-27, after a two-axis code review of `3d54349...HEAD`. Each entry
supersedes part of the body above; the body itself is unchanged.

### Withdrawn

- **US2** — *"name a Poll Group inline while writing my first Poll, so that I am
  not forced through a separate setup step."* Withdrawn: creating a group first
  is acceptable. The story was wrong, not the implementation.

### Superseded

- **US10** — *"add, reorder, edit and remove options while the Poll is a Draft."*
  The **reorder** half is superseded. What is wanted instead is reordering a
  POLL within its group, mirroring the group reordering already built.

  Note this is not the cheaper change: `poll_groups.position` exists, but
  `polls` has no `position` column, so it needs a migration. Option reordering
  within a draft is NOT being built; options keep the order they are entered.

### Deferred, not dropped

- **US41** — *"publish a closed Poll's Result outside the Circle."* The plumbing
  exists (`publish_results`, `Poll::resultIsPublic()`) and is tested, but nothing
  reads it: there is no surface where a non-member sees a published Result.
  Left deliberately until there is a consumer.

### Accepted beyond the original scope

Each was flagged by the review as scope creep, correctly — the spec did not ask
for any of them. All were requested during the build and are accepted:

- **Borda count.** The Out of Scope section deferred it on the grounds that
  "nothing currently needs it". Real use produced the requirement: a ranked
  election where the candidate every voter placed second was eliminated first by
  instant runoff. That section is superseded for Borda only; majority runoff
  remains deferred.
- **A platform-wide display timezone** (`App\Support\DisplayTime`,
  `app.display_timezone`, `Carbon::inDisplayZone()`, Filament). Polls was the
  first feature to accept and show absolute times, which exposed the gap rather
  than creating it.
- **`RatingScalePresentation`** and the star widget, so a scale declares how it
  is drawn instead of being recognised by its name.
- **The forum back-link rework.** The same defect fixed for polls existed in
  forums, having been copied from there.

### Still open from the review

Not decided here; to be raised as tickets:

- Two defects: `updatePoll` restoring a rating scale onto a non-rating question
  (`??` where the rule is `array_key_exists`), and the Electorate drifting when
  `qualifying_date` or `eligibility` is amended without re-snapshotting.
- Two policy questions: whether a visitor may see an open poll (US42 says no,
  the code allows it), and whether an Organiser may switch Attribution off at
  all (Q3a settled only what happens when the flag is on).
- The Standards axis's judgement calls: duplicated slug helpers, an N+1 in the
  tally path, the star component's hardcoded `scores.` property, and the shared
  test schema scaffolding.
