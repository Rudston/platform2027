# 10: Amending a Poll cannot strand a rating scale

**What to build:** Changing an unanswered Poll from a rating ballot to any other
kind removes its rating scale. Today the scale is silently restored, leaving a
single-choice question carrying one — precisely the combination the service
already refuses to CREATE.

The cause is one operator: the amendment path reads the incoming scale with `??`
where the surrounding code deliberately uses `array_key_exists`, and the comment
two dozen lines above states that rule. The compose form always sends an explicit
null when the shape changes, so the null is discarded and the old value returns.

**Blocked by:** 07 (shared test schema builder).

**Status:** resolved

- [x] Switching an unanswered Poll off Rating clears its scale
- [x] Switching one TO Rating without choosing a scale is still refused
- [x] A rating Poll that keeps its shape keeps its scale
- [x] A test asserts the stored question afterwards, not just the absence of an exception
- [x] Every other field on the amendment path is checked for the same `??` mistake

## Answer

One operator, as diagnosed. `updatePoll`'s question write read
`$data['rating_scale_id'] ?? $question->rating_scale_id`, so the explicit null
that `PollModal::updatedShape()` sends on every shape change was discarded and
the old scale returned — storing a single-choice question carrying a rating
scale, which `guardRatingScale` refuses to CREATE.

The rule now lives in one named place rather than being restated at each site:

```php
protected function supplied(array $data, string $key, mixed $current): mixed
{
    return array_key_exists($key, $data) ? $data[$key] : $current;
}
```

Used by the `rating_scale_id` write, the `guardRatingScale` and `guardWindow`
calls, and `updateGroup`'s description.

**The audit (checkbox 5) found a second, live bug.** `updateGroup` had the same
`??` on `description`, which is nullable, and `PollGroupModal` sends an explicit
null for an emptied field (it stores `''` as null) — so a Poll Group description
could be typed but never removed. Fixed with the same helper and its own test.
Strictly outside the five checkboxes, since `updateGroup` is not the poll
amendment path; included because the audit this ticket asked for is what found
it, and it is the identical defect one method away.

Every other `??` on a write path was checked and left, with the reasoning in the
code:

| site | verdict |
| --- | --- |
| `text` ← `prompt` | NOT NULL, form validates required — a null is meaningless |
| `type`, `tally_method` | NOT NULL; and the in-branch `??` is vestigial (`$shape`/`$method` were resolved from this same question, which is non-null there) |
| `require_full_ranking` | NOT NULL, and `??` still passes `false`, so the form's shape-change reset arrives intact |
| `updateGroup` `slug` | uses `isset`, deliberately: both routes bind a group BY slug, so a null one makes its page unreachable. Nothing sends null today; tightening the column is raised by issue 13 |
| `updateGroup` `name`, `position` | NOT NULL; no caller sends null |
| `createPoll` / `createGroup` | `?? null` / `?? false` are creation DEFAULTS, not write-over |
| `publish()` | not an amendment path |
| `eligibility` | `$data['eligibility']->value` would CRASH on an explicit null rather than silently ignore it — a loud failure, not this bug; left alone |

`VotingService` is the only writer of `poll_questions.rating_scale_id` (one
create, one update) — no model mutator, command, seeder or factory touches it, so
the stranding cannot be reintroduced elsewhere.

Tests (in `tests/Services/VotingServiceTest.php`, the service seam): switching
off Rating clears the scale; keeping the shape keeps it; switching TO Rating
stores the chosen scale; clearing a group description works while an omitted key
still means "leave it alone". Each re-reads the stored `PollQuestion` fresh
rather than trusting the returned model (checkbox 4). The existing
illegal-combination test gained the "a refusal is never a partial write"
snapshot instead of a near-duplicate test being added beside it.

Full suite: 316 passed.
