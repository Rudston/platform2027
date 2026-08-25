# Polls — grilling session state

Updated 2026-08-25. Resume by reading this file, `CONTEXT.md`,
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

## Open questions

Each has my recommendation. They are answerable in any order except where noted.

**Q3b — Does hiding attribution cover *that* you responded, or only *what* you chose?**
(a) one flag hides both · (b) roster of who responded always visible, only the
choice hidden · (c) two independent flags.
➡️ (b) — an open roster is what lets a member verify a result without learning
anyone's choice. Load-bearing after Q3a: with no role able to inspect choices,
this is one of only two audit paths members have.

**Q5a — Is a Result frozen or recomputed forever?**
No noun and no table for the output today, so results are implicitly computed
on read forever — meaning a closed election has no fixed answer.
(a) always computed · (b) computed while open, frozen at close · (c) store
every IRV round too.
➡️ (b), and the noun is **Result** (Tally is the verb). Other audit path after
Q3a.

**Q7 — Is a Rating Scale platform or circle vocabulary?**
`poll_rating_scales` has no `circle_id`, so scales are global — but nothing
says who may create one.
(a) platform vocabulary, admin-curated · (b) circle-owned (`circle_id`) ·
(c) global and freely creatable.
➡️ (a) — matches how Themes are handled, and cross-circle comparison only means
anything if "Strongly Agree" is the same row. `theme_suggestions` is the proven
pattern if circles need to propose scales.

**Q11 — Is there a "who can see it" axis?**
Polls have only eligibility (who may respond) and no public state at all, so a
public consultation or a published outcome is currently impossible.
(a) one axis, private · (b) two axes mirroring forums · (c) results-only
publishing.
➡️ (c) — the valuable half is publishing outcomes, and Q3a makes that
privacy-free since nothing is attributed.

## Doc drift to fix in POLLING_SERVICE.md

- Column still `anonymous` -> rename (Q3).
- Comments on `allow_response_update` and `poll_responses` still say
  "participant" -> **Respondent** (Q4).
- Status still `draft | open | closed` -> `draft . published . concluded .
  cancelled` + `archived_at` (Q6, ADR-0001).
- `allowedTallyMethods()` still returns MajorityRunoff and Borda, contradicting
  the deferred section. Should be [Plurality] / [InstantRunoff] / [AverageScore].
- Heading reads "Borda count tally method (two-round system)" - Borda is
  single-round. Only majority runoff is two-round.
- `organiser_id` still present in the schema block -> remove (Q13).
- Tables/enums still named `poll_instances`, `type`, `text` -> `polls`,
  Response Shape, Prompt (Q8, Q9).
