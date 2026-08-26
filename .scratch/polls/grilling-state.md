# Polls — session state

Updated 2026-08-25 (end of build). Resume by reading this file, `CONTEXT.md`,
the ADRs in `docs/adr/`, and `POLLING_SERVICE.md`.

Method: `/mattpocock-skills:grill-with-docs`. Terms resolve into `CONTEXT.md`
as they settle; decisions that are hard to reverse become ADRs.

## Settled (the record is CONTEXT.md — this is just the index)

| | Decision |
|---|---|
| Q1 | **Poll** is the genus. Election / Proposition describe a Poll's shape, not stored types. Survey is a separate future service. |
| Q2 | Eligibility is the *same concept* as forum participation, but keeps its own enum so the two can diverge. Duplication accepted deliberately. |
| Q3 | `anonymous` renamed to what it is. **Anonymous ≠ Secret**: identity is always stored. Secret Ballot is unbuilt. |
| Q3a | Attribution is withheld from **everyone** in the UI — members, organiser, platform admin, superadmin. Carve-out: a user may see their own response. |
| Q4 | Poll answerers are **Respondents**, never "Participants" (taken by forums). |
| Q5 | Response Shape and Tally Method are two axes, with legal pairings pinned on the shape (`allowedTallyMethods()`). |
| Q6 | Status records *why a Poll stopped early*, never *whether it is open*. Stored: `draft · published · concluded · cancelled`. Derived: Scheduled / Open / Closed. |
| Q6a | `concluded` = ended early, has a Result. `cancelled` = void, never tally. Both stamp `closes_at`. |
| Q6c | A Poll that runs out its clock stays `published`. No `closed` case, no cron job. |
| Q6d | `archived` dropped from the enum; `archived_at` timestamp instead, orthogonal to how the Poll ended. |
| Q5b | Majority runoff **deferred** — it is not a Tally Method at all (spawns a second Poll rather than computing over the first). Borda deferred too: nothing needs it. |
| Q13 | `organiser_id` dropped as an accidental duplicate. `created_by` is the Organiser. |
| Q13a | Conclude/Cancel = **creator OR circle admin**, mirroring `canCreateDiscussion`. |
| Q13b | Creator authority requires still being a member; circle admins are unconditional. |
| Q2a | Poll eligibility mirrors forum visibility vocabulary: `PollEligibility::Private` / `::Internal` (no Public case). Separate enum, matching words. |
| Q8 | `poll_instances` -> `polls`. "Instance" is not a domain word. |
| Q9 | "Question" is structural only, never user-facing. The instruction text is a **Prompt**. |
| Q10 | Display name **"Polls"**. `services.key = 'voting'` unchanged (stable handle). |
| Q12 | Eligibility = snapshot at a **Qualifying Date** (defaults to publish, may be earlier, never later), materialised into a `poll_electorate` table at publish. Casting requires being in the Electorate AND still a member — tested at vote time, never at tally time. |
| Q4a | Settled by consequence of Q12: **Electorate** = the entitled set, so **Respondent** keeps its has-responded meaning. |
| Q3b | Attribution hides *what* you chose, never *that* you responded. The **Roster** stays visible. |
| Q5a | **Result** is computed while Open and frozen at Close; Tally is the verb. Recomputation checks a Result, never replaces it. |
| Q3c | Roster: live COUNT while Open, NAMES only after Close — verification without a live list of holdouts. |
| Q5c | A frozen Result holds per-option totals, turnout and the winner. Not IRV elimination rounds (recomputation reproduces them). |
| Q7 | Rating Scales are **platform vocabulary**, admin-curated and seeded. Circles pick, never mint. |
| Q11 | **Results-only publishing**: a Poll is member-only while it runs; a flag publishes the Result once it Closes. |
| Q14 | Polls get BOTH tags (`HasTags`, existing Theme vocabulary) and a `poll_groups` container. Tag = what it is about, travels across Circles; Group = which local effort it belongs to. |
| Q14a | A Poll Group is organisational only — no visibility, no status, never gates its Polls. |
| Q14b | Group membership is REQUIRED: every Poll belongs to exactly one Group. |
| Q14c | Groups are archived, never deleted; archiving leaves the Polls listed. |
| Q14d | No default/"General" Group. The Poll form picks or creates one inline. |
| Q15 | **No stored `kind`.** Election stays a description, not data; add the enum only if something real depends on it. Q1 holds. |

## Open questions

None. The frontier is empty: every branch of the design tree was visited.

Deferred by decision (not open questions): majority-runoff and Borda tally
methods, Surveys (multi-question instances, free text, branching), completion
actions, secret ballots, a publicly-viewable live Poll, and a stored `kind`
on Poll (Q15 - add it only when something depends on it).

Deferred as UNRESOLVED rather than unwanted: notifications. Nothing announces
that a poll opened, closes soon, has a result, or was cancelled - which is the
biggest practical threat to turnout. POLLING_SERVICE.md records the four
candidate events and the open questions (consent, volume, the reminder job vs
ADR-0001's no-cron rule, idempotency, not targeting non-respondents, and the
freeze-before-send interaction). Needs a decision pass before any code.

## Doc drift

None. `POLLING_SERVICE.md` was reconciled against every decision above.
Terminology is governed by `CONTEXT.md`; ADRs 0001-0003 carry the three
decisions whose reasoning is invisible in the code.

## Where the work stands

The design tree is closed and the service is BUILT: migrations, enums, models,
a pure tally, the VotingService handler, the UI, and 60 tests across the three
seams. Full suite green (230 tests). Migrations and the rating-scale seeder
have been run against the dev database.

Commits, oldest first:
  8377782  glossary + ADRs 0001-0002
  40f448a  reconcile POLLING_SERVICE.md
  dd9c198  finish the domain model (+ ADR-0003)
  3d54349  the spec
  1d1ee1b  build the Polls service
  7ff84a1  document translatable services.name
  ab85b05  seed the rating scales
  f0f81fa  tag picker on polls
  933c7a3  community page tag row refresh
  179e7ca  edit an unanswered poll
  70bbca2  notifications recorded as deferred-unresolved

## Outstanding — one ticket each in ./issues/

| # | Ticket | Status |
|---|--------|--------|
| 01 | Document the Polls service in CLAUDE.md | **resolved** |
| 02 | Decide the notification model | needs-info (blocked: messaging service) |
| 03 | Portuguese label for the Polls service | **resolved** (keep Votações) |
| 04 | A result only freezes when someone visits the poll | **resolved** |
| 05 | No way to reorder poll groups | **resolved** |
| 06 | Polls has no Portuguese translation | ready-for-human (placeholder in place) |

01 and 03 are done. 02 turned out to be BLOCKED, not merely undecided: it
waits on a platform messaging service that is planned but undefined, which will
own channels and user preferences. Polls must not grow their own delivery path
in the meantime. 01, 03, 04 and 05 are done. Only 02 (blocked on the messaging service) and
06 (needs a Portuguese speaker) remain — neither is actionable here.

## Not outstanding — deferred by decision

Majority-runoff and Borda tally methods, Surveys (multi-question, free text,
branching), completion actions, secret ballots, a publicly-viewable live Poll,
and a stored `kind` on Poll. Each is recorded with its reasoning in
POLLING_SERVICE.md; none needs revisiting unless something new depends on it.
