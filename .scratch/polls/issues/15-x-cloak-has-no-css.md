# 15: Elements hidden with x-cloak flash before Alpine boots

**What to build:** Content marked to stay hidden until Alpine initialises
actually stays hidden. It does not: CLAUDE.md records that this project has NO
`x-cloak` CSS and that initial state is server-rendered to avoid the flash — but
several views use `x-cloak` anyway, so every one of them paints briefly on load.
The star rating shows all five point labels at once; a poll's Timing and the
group menus flash open.

Pre-dates Polls — forum views do the same — so this ticket carries a decision
about scope as much as a fix.

**The decision:** add the one-line `[x-cloak] { display: none }` rule, which
makes every existing use work at once and means CLAUDE.md's note must be
rewritten; or remove the `x-cloak` uses and server-render their initial state,
which honours the documented approach but must be done at each site. Pick one
and make the documentation and the code agree — today they do not.

**Blocked by:** 07 (shared test schema builder).

**Status:** ready-for-agent

- [ ] No element marked `x-cloak` is visible before Alpine initialises
- [ ] CLAUDE.md and the code agree about which approach this project uses
- [ ] Whichever is chosen is applied consistently, not only to the poll views
