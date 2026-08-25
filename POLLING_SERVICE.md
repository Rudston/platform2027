# Polling Service — design summary

Status: design finalized, not yet turned into a Claude Code build prompt.

Terminology in this document is governed by `CONTEXT.md`; two decisions below
are recorded as ADRs (`docs/adr/0001` on poll status, `docs/adr/0002` on the
electorate snapshot). Where this document and those disagree, they win.

## Scope

Covers **Voting and Polling** as one `CircleServiceContract` service
("Voting & Polling Services" in the partnership brief) — NOT a full
Survey system. Handles:

1. FPTP (single-choice) elections — e.g. voting for a circle admin.
2. Ranked-choice elections.
3. Single-choice propositions — e.g. "how should we handle X?"
4. Rating-scale polls — rating several proposals on a fixed scale.

The schema is deliberately shaped so a future **Surveys** service
(multiple questions per instance, free-text responses, no forced tally,
per-question result views) can reuse the same tables later without
restructuring. Voting/Polling's own creation UI will only ever produce
an instance with exactly one question — that's a UI-level restriction,
not a schema-level one.

## Schema

```
polls
  id
  circle_id            FK -> circles.id, cascade
  title                string
  description          text, nullable
  eligibility           string   -- 'private' | 'internal'
                        -- mirrors ForumGroupVisibility's vocabulary for
                        -- the same two rules (no 'public' case). Resolved
                        -- via ONE method, Poll::electorate(), so a future
                        -- "extend to all circles from a locatable
                        -- downward" rule changes one place, not
                        -- scattered queries
  qualifying_date       timestamp, nullable
                        -- membership cut-off the electorate is drawn
                        -- from. Defaults to the publish moment; may be
                        -- set EARLIER (so joining after a poll is
                        -- announced confers no vote), never later.
  allow_response_update  bool, default false
                        -- can a Respondent change their submitted
                        -- response before the poll closes?
  hide_voter_identities  bool, default true
                        -- display rule ONLY: identity is always stored.
                        -- Withheld from EVERYONE — members, the
                        -- organiser, platform admins, superadmins — the
                        -- sole exception being a user viewing their own
                        -- response. NOT a secret ballot; see CONTEXT.md.
  opens_at              timestamp, nullable
  closes_at             timestamp, nullable
  status                string   -- draft | published | concluded
                        -- | cancelled. Records WHY a poll stopped early,
                        -- never WHETHER it is open — see ADR-0001.
                        -- Open/Closed are derived from the clock.
  archived_at           timestamp, nullable
                        -- filed away; orthogonal to how the poll ended
  created_by            FK -> users.id, nullOnDelete
                        -- the Organiser. May Conclude/Cancel this poll
                        -- while still a member of the circle; circle
                        -- admins may do so unconditionally.
  settings              json, nullable
                        -- reserved for a future "completion action"
                        -- (e.g. grant a role on election result, mark
                        -- a proposition passed) — no defined shape yet,
                        -- same pattern as forum_groups.settings
  timestamps

poll_electorate
  id
  poll_id               FK -> polls.id, cascade
  user_id               FK -> users.id, cascade
  timestamps
  -- written ONCE at publish, from the append-only circle_memberships
  -- log as of qualifying_date. Snapshotted rather than derived: see
  -- ADR-0002 (internal_role_approved is mutated in place and keeps no
  -- history, so a past electorate cannot be reconstructed). Casting a
  -- response requires a row here AND still-active membership, tested at
  -- vote time, never at tally time.

poll_questions
  id
  poll_id               FK -> polls.id, cascade
  position              int   -- order within the instance; always 0
                        -- for Voting/Polling's single-question case
  text                  text   -- the Prompt: the instruction shown to
                        -- the Respondent, e.g. "Select ONE from:".
                        -- "Question" is structural and never surfaces
                        -- in the UI — a poll has exactly one.
  type                  string   -- the Response Shape:
                        -- 'single_choice' | 'ranked_choice' | 'rating'
                        -- (a future 'free_text' type is anticipated
                        -- for Surveys — not built now)
  tally_method          string   -- see "Type vs. tally_method" below —
                        -- must be one of type's allowedTallyMethods()
  require_full_ranking  bool, default false
                        -- ONLY meaningful when type = ranked_choice:
                        -- if true, a response must rank every option
                        -- (no partial/incomplete rankings allowed)
  rating_scale_id       nullable FK -> poll_rating_scales.id
                        -- ONLY set when type = rating
  timestamps

poll_options
  id
  poll_question_id      FK -> poll_questions.id, cascade
  label                 string   -- candidate name / proposal text
  position              int
  timestamps

poll_rating_scales
  id
  name                  string   -- e.g. "5-point agreement scale"
  timestamps

poll_rating_scale_points
  id
  poll_rating_scale_id  FK -> poll_rating_scales.id, cascade
  label                 string   -- e.g. "Strongly Agree"
  value                 int      -- numeric value used in tallying
  position               int     -- display order
  timestamps

poll_responses
  id
  poll_question_id      FK -> poll_questions.id, cascade
  user_id               FK -> users.id, cascade
  submitted_at           timestamp
  timestamps
  -- one row per Respondent per question. If allow_response_update
  -- is true on the parent instance, this row is updated in place
  -- (and submitted_at refreshed) rather than a new row inserted.

poll_response_items
  id
  poll_response_id      FK -> poll_responses.id, cascade
  poll_option_id        FK -> poll_options.id, cascade
  rank                  nullable int    -- used for ranked_choice
  rating_scale_point_id  nullable FK -> poll_rating_scale_points.id
                        -- used for rating
  timestamps
  -- single_choice: exactly ONE item per response, rank and
  --   rating_scale_point_id both null.
  -- ranked_choice: one item per option the Respondent ranked
  --   (all options, if require_full_ranking is true), each with
  --   a distinct rank.
  -- rating: one item per option, each pointing at the
  --   rating_scale_point_id chosen for that option.
```

## Type vs. tally_method — two concepts, constrained

`type` and `tally_method` are related but distinct axes, not one
concept wearing two column names:

- `type` (`PollQuestionType` enum) describes **the shape of a
  response** — one item, several ranked items, or several scored items.
- `tally_method` (`TallyMethod` enum, separate) describes **the
  algorithm applied to those responses** — plurality, instant-runoff,
  Borda count, majority-with-runoff, average score.

They correlate in all four worked examples below, but they're not
always the same thing: `single_choice` ballots could plausibly be
tallied by plain plurality OR by majority-with-runoff (>50% required,
else re-poll); `ranked_choice` ballots could be tallied by
instant-runoff OR Borda count. Collapsing the two into one enum would
mean adding a new case every time a new counting algorithm is wanted
over an already-supported ballot shape — the wrong axis to make rigid.
Leaving them as two fully independent columns would instead admit
nonsense combinations (`single_choice` + `average_score` has nothing to
average).

Resolution: keep both, but constrain legal pairings in exactly one
place, via a method on the type itself — the same pattern already used
for `allowedInternalRoles()` on a community type:

```php
enum PollQuestionType: string
{
    case SingleChoice = 'single_choice';
    case RankedChoice = 'ranked_choice';
    case Rating       = 'rating';

    public function allowedTallyMethods(): array
    {
        return match($this) {
            self::SingleChoice => [TallyMethod::Plurality],
            self::RankedChoice => [TallyMethod::InstantRunoff],
            self::Rating       => [TallyMethod::AverageScore],
        };
    }
}
```

Both alternative methods that make the axes visibly independent
(`majority_runoff`, `borda_count`) are deferred, so in v1 the two columns
correlate 1:1. They stay separate anyway: with them apart, adding Borda later
is a one-line array edit; collapsed into one enum it becomes a schema change
plus a data migration over live poll records.

The question-creation UI picks `type` first, then only offers
`tally_method` options from that type's `allowedTallyMethods()` — no
invalid pairing is ever reachable, and adding a new algorithm later
(e.g. Borda count for ranked-choice) is a one-line array edit, not a
schema change.

## Worked examples

**1. FPTP — vote for Circle Admin**
`poll_question.type = single_choice`, `tally_method = plurality` — the
only legal choice in v1, `majority_runoff` being deferred. Options: candidate a/b/c/d. Each response has exactly one
`poll_response_item`. Tally = count of items per option.

**2. Ranked choice — vote for Circle Admin**
`poll_question.type = ranked_choice`, `tally_method = instant_runoff` —
the only legal choice in v1, `borda_count` being deferred —
`require_full_ranking` — admin's choice, per instance. Options:
candidate a/b/c/d (same options table, no structural change from case 1).
Each response has one item per ranked option, each item carrying a
distinct `rank` (1 = top choice). If `require_full_ranking` is true,
validation requires every option ranked, 1..N, no duplicates.

**3. Proposition vote — "How should we handle X?"**
Structurally identical to case 1 — `single_choice` / `plurality` — just
different option labels (proposals, not candidates). Not a new shape.

**4. Rating poll — rate several proposals**
`poll_question.type = rating`, `tally_method = average_score`,
`rating_scale_id` -> a shared, reusable `poll_rating_scales` row (e.g.
Strongly Disagree..Strongly Agree, defined once, referenced by any
question that needs it — not typed inline per instance). Each response
has one item per option, each pointing at the chosen
`rating_scale_point_id`.

## Eligibility and the electorate

Eligibility is the same underlying concept as forum participation — a
membership test against the circle — kept in its own enum so the two can
diverge later, and named with ForumGroupVisibility's vocabulary so the shared
origin stays legible:

- `private` — any active `circle_memberships` row on this circle.
- `internal` — active membership AND
  `CircleMembership::hasApprovedInternalRole()` is true.

There is no `public` case: a poll is never answerable from outside its circle.

**The electorate is snapshotted, not derived.** At publish, one pass over the
append-only `circle_memberships` log writes a `poll_electorate` row per
eligible user, as of `qualifying_date`. That table is authoritative from then
on. Deriving instead would be wrong for `internal` polls —
`metadata.internal_role_approved` is mutated in place and keeps no history, so
a query answers with today's approvals for a past date. See ADR-0002.

Casting a response requires **both** a `poll_electorate` row **and** current
active membership, tested when the response is given. Nothing is ever filtered
out at tally time, so no published count moves after the fact; a member who
leaves after voting keeps their vote, and one who leaves before voting simply
never casts.

Resolution lives behind one method (`Poll::electorate()`) so a later
requirement — extending eligibility to all members of every location circle
from a given locatable downward in the hierarchy — is a change in one place,
not a schema change or a scattered rewrite.

## Explicitly deferred (not part of this spec)

- Free-text question type and multi-question instances (Surveys).
- Branching/conditional question logic (Surveys-only concern).
- Any concrete "completion action" behavior — only the reserved
  `settings` json column exists for now.
- Extending eligibility beyond the two states above.

**Majority runoff tally method (two-round system) is also Explicitly deferred:**

**Round 1** — same ballot shape as plurality: everyone picks one option.
- If a candidate gets **more than 50%** of valid votes, they win outright — no second round needed.
- If **nobody** clears 50% (common with 3+ candidates splitting the vote), a **runoff round** is triggered — typically just between the top two finishers from round 1. Everyone votes again, this time choosing only between those two. Whoever gets more votes in round 2 wins (a two-candidate race always produces a majority, barring an exact tie).

Worth flagging clearly: this is **not** the same kind of "tally method" as the other three. Plurality, instant-runoff, Borda, and average-score are all pure computations over responses that already exist — you tally once and you're done. Majority runoff, if no one clears 50% in round 1, requires:

- Determining a reduced candidate set (usually top 2, though some rulesets use a different threshold/count).
- Creating a **second poll** — a new voting window, re-notifying eligible voters, waiting for new responses.
- Some policy for what happens if round 2 is *also* a tie.

That's a genuinely different shape from "run an algorithm over `poll_response_items`" — it needs a way to say "this instance is a runoff triggered by that instance" (e.g. a nullable `runoff_of_poll_id` self-reference on `polls`), plus the operational logic to spin one up automatically or prompt an admin to.

**Borda count tally method is also Explicitly deferred:**

