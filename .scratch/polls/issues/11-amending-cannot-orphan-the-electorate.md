# 11: Amending a Poll cannot orphan its Electorate

**What to build:** Changing who is entitled to respond actually changes who is
entitled to respond. Today the membership cut-off and the eligibility rule can
both be amended on a published Poll while the stored Electorate — snapshotted at
publish — stays as it was, so the Poll's stated cut-off and its real electorate
silently disagree.

That denominator is the whole point of ADR-0002: the Electorate is snapshotted
BECAUSE it cannot be derived afterwards, so an amendment that moves the cut-off
without re-snapshotting leaves a figure nothing can reconstruct.

Amendment is only possible while a Poll has no responses, so re-snapshotting
disenfranchises nobody who has already acted.

**Blocked by:** 07 (shared test schema builder).

**Status:** resolved

- [x] Amending the membership cut-off on a published Poll re-snapshots the Electorate
- [x] Amending the eligibility rule does the same
- [x] Amending anything else leaves the Electorate untouched
- [x] The re-snapshot obeys the same rules as the original, including approved internal roles only
- [x] A test moves a cut-off across a member's join date and asserts they enter or leave the Electorate

## Answer

`VotingService::updatePoll()` re-snapshots the Electorate when either of its
inputs actually moves — the Qualifying Date or the eligibility rule — through
`snapshotElectorate()`, the same code publication uses, so checkbox 4 holds by
construction rather than by a parallel implementation.

Three supporting changes were needed:

- **`syncWithoutDetaching` → `sync`.** The old call could never REMOVE anyone,
  so a narrowed cut-off could not drop the members it no longer covered
  (checkbox 5's "leave" direction). Its comment said as much: "never silently
  removes an entitlement". That safety property is now enforced rather than
  described — `snapshotElectorate` refuses outright to run on a Poll with any
  response, since it replaces the stored set and removing an exercised
  entitlement is the one thing it must never do. Both callers already guaranteed
  zero responses; the method is now safe on its own terms.
- **`guardQualifyingDate()`**, extracted from `publish()` and shared. A future
  date cannot be resolved without a scheduled job (ADR-0002), and once an
  amendment re-snapshots it must obey the same rule — checkbox 4. It also
  refuses to REMOVE the date from a published Poll: a stored Electorate with
  nothing stating what it was resolved from is precisely the disagreement this
  ticket is about. Reachable from the UI (the field can be emptied), so
  `PollModalTest` covers it landing as a form error rather than a 500.
- **Comparison at the precision the form can express.** Found in review, and a
  bug in the first cut of this fix: `DisplayTime::toInput()` formats
  `'Y-m-d\TH:i'`, so the form receives the stored date truncated to the minute
  and sends that copy back on EVERY save. Compared literally, a title-only edit
  moved the stated Qualifying Date up to 59 seconds earlier AND re-resolved the
  Electorate — breaking checkbox 3 through the UI while the service-level test
  passed. Same minute now means unchanged, and the stored value is left alone.
  Pinned by `PollModalTest::test_an_unrelated_edit_moves_neither_the_qualifying_date_nor_the_electorate`,
  which fails against the literal comparison.

The trigger is keyed on "has been published", not `status === Published`:
`guardAmendable` admits a Concluded or Cancelled poll nobody answered, and such a
poll still carries an Electorate. Tested.

**Docs corrected** (both review axes flagged that this change had outgrown them):
ADR-0002 gains a Consequences paragraph — the snapshot is retaken when its inputs
move, never derived on read, never scheduled — and its "authoritative from then
on" clause is qualified. `CONTEXT.md`'s **Electorate** entry said flatly "it
never shrinks", now true from the first response; **Qualifying Date** said "never
later", which was ambiguous — it means never in the FUTURE, and an amendment may
move it either way while the Poll has no responses. `CLAUDE.md`'s "Written once
at publish" is now accurate.

Also fixed: my edits in issues 10 and 11 had stranded the docblocks of
`guardAmendable()` and `supplied()` by inserting new methods between a docblock
and its function. Reattached.

Noted, not fixed (unrelated, pre-existing): a poll published and concluded within
the same second has `opens_at == closes_at`, so ANY later amendment fails
`guardWindow` with "cannot close before it opens". Nothing to do with the
Electorate, but it makes such a poll unamendable with a confusing message.

Full suite: 326 passed.
