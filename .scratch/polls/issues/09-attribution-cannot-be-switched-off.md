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

**Status:** ready-for-agent

- [ ] The compose form offers no control over attribution
- [ ] No code path returns another user's choice, whatever the stored data says
- [ ] A Respondent can still see their own response
- [ ] The Roster still shows WHO responded once a Poll has Closed
- [ ] A test covers the case the spec named: withheld from the Organiser and from a superadmin
- [ ] The keep-or-drop decision is recorded, and CONTEXT.md still reads true either way
