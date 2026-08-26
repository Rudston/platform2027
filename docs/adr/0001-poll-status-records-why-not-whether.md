# Poll status records why a Poll stopped, never whether it is open

A Poll has both a scheduled response window (`opens_at` / `closes_at`) and a
`status`. If status also carried `open` / `closed`, two columns would assert
the same fact and could disagree — a Poll displaying "open" while refusing
votes, which users report as broken. So `status` never describes availability:
whether a Poll is accepting responses is always derived from the clock, and
status records only the editorial and exceptional facts no timestamp can
carry.

Stored: `draft` (still being composed), `published` (released), `concluded`
(an organiser ended it early; it ran and has a Result), `cancelled` (void —
never tally it, no Result). Derived: **Scheduled** (published, `opens_at`
ahead), **Open** (published, inside the window), **Closed** (everything else).
Both `concluded` and `cancelled` must also stamp `closes_at`, so status
annotates the clock rather than competing with it.

## Considered options

- **Stored `open` / `closed`, flipped by a scheduled job.** Rejected: the job
  exists only to write down something already knowable, and any lag between
  expiry and the job running is a window where the two sources disagree.
- **A single `deactivated` case covering both early endings.** Rejected: "we
  decided this" and "this never happened" have opposite consequences for
  tallying, and one word cannot carry both. `deactivated` also already means
  *switched off* on `ForumGroupStatus`, so reusing it here would give the same
  word opposite senses in two services.
- **`archived` as a status case** (as `forum_groups` has it). Rejected: it
  answers a different question — "should this appear in lists?" — and is not
  exclusive with the others, so writing it would erase whether the Poll had
  been concluded or cancelled. Archiving is `archived_at`, a nullable
  timestamp, orthogonal to status.

## Consequences

**A scheduled job may record a Result, but must never write poll STATE.** This
rule is not "no cron" — the scheduler already runs `requests:expire` and
`comments:check-moderation`, and `polls:freeze-results` was later added to
freeze the Result of polls nobody visited (insurance against the tally code
changing under a settled decision). That job writes `result` and
`result_frozen_at` and nothing else. Extending it to touch `status`,
`closes_at` or `archived_at` would make the clock stop being the authority and
reintroduce exactly the disagreement this decision removes.

A Poll that simply runs out its clock keeps `status = published` forever, so a
finished election and a live one are indistinguishable by status alone. This
is deliberate: nothing exceptional happened, so nothing is recorded. Listing
finished Polls compares timestamps rather than filtering on status.

There is no `suspended` case. If pause-and-resume is ever wanted, that is the
name to use — it matches `CircleStatus::Suspended`.
