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

**Status:** ready-for-agent

- [ ] Amending the membership cut-off on a published Poll re-snapshots the Electorate
- [ ] Amending the eligibility rule does the same
- [ ] Amending anything else leaves the Electorate untouched
- [ ] The re-snapshot obeys the same rules as the original, including approved internal roles only
- [ ] A test moves a cut-off across a member's join date and asserts they enter or leave the Electorate
