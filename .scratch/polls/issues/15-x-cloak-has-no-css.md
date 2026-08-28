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

**Status:** resolved

- [x] No element marked `x-cloak` is visible before Alpine initialises
- [x] CLAUDE.md and the code agree about which approach this project uses
- [x] Whichever is chosen is applied consistently, not only to the poll views

## Answer

Chose the one-line CSS rule over per-site removal. Removing `x-cloak` from
every view (poll star-rating, poll Timing/group menus, community page, forum
views) would be an ongoing whack-a-mole against a well-known Alpine idiom.

- `resources/css/app.css` now ships `[x-cloak] { display: none !important }`,
  near the top of the file. Alpine removes the attribute when it boots.
- `app.css` is loaded by every layout (`app`, `main`, `public`, `guest`,
  `dashboard`, `authenticated`), so poll and forum views are covered at once —
  nothing per-site.
- CLAUDE.md's Content Blocks note rewritten: it previously said the project
  has no `x-cloak` CSS and that initial state is server-rendered; it now
  documents `x-cloak` as the project's approach and points at the rule.
- Verified with `npm run build` — the compiled `public/build/assets/*.css`
  contains the rule.
