# 09: Attribution cannot be switched off

**What to build:** No one — Organiser, platform admin, superadmin — can see
which option another person chose, and no setting anywhere changes that. Today
the compose form offers the Organiser a checkbox that turns the guarantee off.

CONTEXT.md states the rule without qualification: attribution is *"withheld from
EVERYONE… The sole exception is a user viewing their own response."* US35 asks
for *"a real guarantee and not a courtesy"* — and a guarantee an Organiser can
untick is exactly a courtesy.

Withholding attribution has never concealed *that* someone responded: the Roster
is unaffected by this ticket.

**One decision this ticket carries:** keep the stored flag and remove only the
control, or drop the flag as well. Recommended: DROP it. A column that may only
ever hold one value misrepresents what is configurable, and leaves the rule
flippable directly in the database — which is how an unrelated stale-result bug
reached production data earlier. If an open ballot is ever wanted, it deserves
its own decision rather than a switch that quietly already exists.

**Blocked by:** 07 (shared test schema builder).

**Status:** resolved

- [x] The compose form offers no control over attribution
- [x] No code path returns another user's choice, whatever the stored data says
- [x] A Respondent can still see their own response
- [x] The Roster still shows WHO responded once a Poll has Closed
- [x] A test covers the case the spec named: withheld from the Organiser and from a superadmin
- [x] The keep-or-drop decision is recorded, and CONTEXT.md still reads true either way

## Answer

**Decision: DROPPED the column**, as recommended — recorded in
`docs/adr/0004-attribution-has-no-setting.md` with the options considered.
Migration `2026_08_27_000001_drop_hide_voter_identities_from_polls_table`; its
`down()` restores the column but not the data, since every row held the same
value.

Removed with it: the compose checkbox (`PollModal` property, mount hydration,
save payload, and the Blade block — replaced by a comment saying why there is no
control), the `VotingService` field in `createPoll`/`updatePoll` and its docblock
array shape, the `polls.poll.hide_voter_identities` and `poll.hide_help` lang
keys in `lang/en` and `lang/pt`, and the model cast.

`PollResponse::isChoiceVisibleTo(?User)` lost its `Poll` argument and its flag
branch: it answers true only for the Respondent. **The guarantee is structural
before it is a predicate** — audited every read of `poll_responses` /
`poll_response_items`:

- `PollPage::hydrateExistingResponse()` is the ONLY place that reads what
  somebody chose. Its query is scoped to the viewer, and it now calls
  `isChoiceVisibleTo()` as well, so widening that query later cannot silently
  open the ballot. (Review caught that the predicate had no caller while the docs
  called it "the whole rule" — it is now wired, and the docs describe it
  accurately.)
- `VotingService::tally()` maps responses into `Ballot`/`Mark`, which carry
  option ids, ranks and values — identity is dropped at that boundary.
- `Poll::roster()` selects `user_id` only, never items. `respondentCount()`,
  `PollGroupPage::respondentCounts()` and `hasResponded()` are counts and
  existence checks. There is no poll Filament resource or console read.

`tests/Feature/PollAttributionTest.php` — 8 tests, 25 assertions. The guarantee
is asserted against the Organiser, a circle admin, a platform admin, a
superadmin, a fellow member and a visitor. The three tests for what must NOT
change (a Respondent sees their own ballot; the Roster names Respondents once
Closed; `user_id` is still stored) passed before the change and still pass.

Docs that also said the flag existed, and now do not: `CONTEXT.md` (Attribution
no longer opens with "Whether…"), `CLAUDE.md` (the Attribution section, three
Common Mistakes entries, and "THREE DECISIONS THAT LOOK LIKE BUGS" → FOUR),
`POLLING_SERVICE.md` (the `polls` schema block and the results section), and
ticket 06's translator note, which was instructing a human to translate a key
that no longer exists.

Full suite: 312 passed.
