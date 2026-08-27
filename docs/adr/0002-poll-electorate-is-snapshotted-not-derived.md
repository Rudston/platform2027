# A Poll's electorate is snapshotted at publish, not derived from memberships

`circle_memberships` is append-only — rows are never deleted, leaving sets
`left_at`, and `joined_at` is kept — so membership on any past date looks
derivable, and a stored electorate looks like redundant denormalisation. It
isn't. A Poll materialises its Electorate into `poll_electorate` (one row per
user) at publish, filtered by the Poll's Qualifying Date, and that table is
authoritative from then on — re-resolved only by an amendment that moves one of
its two inputs, never derived on read (see Consequences).

The reason lives in a different table than the one you would be tempted to
delete: `circle_memberships.metadata.internal_role_approved` is **mutated in
place** (pending → approved → rejected) and keeps no history. For a Poll
restricted to members with an approved internal role, deriving the electorate
from the membership log silently answers with *today's* approvals for a date in
the past — a wrong electorate, on exactly the Polls whose eligibility is most
restrictive. Materialising also keeps the turnout denominator stable while a
Poll is open, which is what lets a member verify a published count.

## Considered options

- **Derive on demand from `circle_memberships`.** Rejected for the
  `internal_role_approved` gap above, and because a live query makes the
  denominator move under readers mid-Poll.
- **A JSON array of user ids on the Poll.** Rejected: it cannot be joined to
  `users` (so the visible roster cannot render names or paginate), cannot be
  indexed for the per-vote eligibility check, and is unbounded — a provincial
  or national location Circle's electorate runs to thousands of ids.
- **Tally only responses from users who are still members** (proposed as a
  defence against people joining briefly to swing a vote). Rejected: it does
  not stop that attack, since the attacker need only stay; and because
  memberships are capped and swappable, an ordinary member who joins another
  Circle must drop one — silently losing a vote they already cast. The
  infiltration concern is answered instead by setting the Qualifying Date
  before the Poll was announced, which prevents the vote rather than annulling
  it afterwards.

## Consequences

Eligibility is tested when a response is cast — in the Electorate **and** still
an active member — never when the Poll is tallied. No response is ever removed
retroactively, so published turnout figures do not move after the fact.

The Electorate includes members who have since left and can no longer vote, so
turnout reads slightly low in a Circle with churn. This is accepted as honest:
they were entitled when the Poll opened.

The Qualifying Date must not be in the future, so materialisation always
happens synchronously — and no scheduled job is needed.

**The snapshot is RETAKEN when its own inputs move.** Amending the Qualifying
Date or the eligibility rule re-resolves the Electorate from the membership log,
through the same code as the original, so a Poll's stated cut-off and its real
Electorate can never disagree. Without this, an amendment left a denominator
nothing could reconstruct — which is this decision's whole premise, since the
set cannot be derived after the fact.

It is safe because amendment requires zero responses (`Poll::isAmendable`), so a
re-snapshot disenfranchises nobody who has already acted; `snapshotElectorate`
refuses outright to run on a Poll with any response, because it REPLACES the
stored set (`sync`) and removing an exercised entitlement is the one thing this
must never do. Any other amendment leaves the Electorate untouched — compared at
the precision the compose form can express, since a `datetime-local` field
cannot carry seconds and would otherwise report an edit on every save.
