# Attribution has no setting

Who chose what is withheld from everyone, always. There is no per-poll switch,
no `polls.hide_voter_identities` column, and `PollResponse::isChoiceVisibleTo()`
takes no Poll and consults no flag: it answers true only for the Respondent
themselves. Not the Organiser, not a circle admin, not a platform admin, not a
superadmin.

The guarantee is structural before it is a predicate. Only one place in the
application reads what somebody chose — `PollPage::hydrateExistingResponse()`,
whose query is scoped to the viewer and which then asks `isChoiceVisibleTo()`
anyway, so widening that query later cannot silently expose another
Respondent's answer. Every
other read is already identity-free: the tally maps responses into `Ballot` and
`Mark`, which carry option ids, ranks and values and nothing else, and
`Poll::roster()` selects `user_id` alone, never response items.

A reader will look for the setting, because every neighbouring behaviour has
one — `publish_results`, `allow_response_update`, `eligibility` — and will
assume the missing column is an oversight. It isn't. The column existed, the
compose form offered it as a checkbox, and both were removed on purpose.

US35 asks for "a real guarantee and not a courtesy". A guarantee an Organiser
can untick is a courtesy, and one a DBA can flip with an UPDATE is barely that.

## Considered options

- **Keep the column, remove only the checkbox.** Rejected. A column that may
  only ever hold one value misrepresents what is configurable: the next reader
  writes code branching on it, and the rule stays flippable in the database with
  nothing in the application to stop it. An earlier stale-result bug reached
  production data by exactly that route.
- **Keep the checkbox, default it on.** Rejected outright — this is the defect
  the ticket names. Members cannot verify a promise that a single click revokes.
- **Drop the column and the control.** Chosen.

## Consequences

Attribution is now a property of the platform rather than of a Poll, so it
cannot drift between Polls and needs no migration to stay true.

**This is still NOT a secret ballot.** `poll_responses.user_id` is always
written; withholding is a display rule, and database access reveals everything.
Never describe a Poll as anonymous or secret — see CONTEXT.md, which defines
Secret Ballot precisely so the distinction has a name.

What is withheld is only WHAT someone chose, never THAT they responded. The
Roster is untouched: a live count while the Poll is Open, names once it Closes.
That is deliberate — the Roster is what lets a member check a published count,
their own name being on it.

If Attribution is ever wanted as a per-Poll choice — a show of hands, a public
endorsement — it is a new decision with its own ADR, and it must earn a name in
CONTEXT.md rather than reappear as a checkbox. The
`down()` migration restores the column but not its data, since every row held
the same value.
