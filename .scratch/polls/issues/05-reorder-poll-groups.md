# No way to reorder poll groups

Status: resolved
Type: task

## Why

`poll_groups.position` exists, is written on create (defaulting to 0), and the
Polls tab already orders by it. Nothing sets it to anything else, so every
group in a circle sits at 0 and the ordering falls through to name.

Spec user story 5: "As a circle admin, I want to order Poll Groups, so that the
active effort appears above dormant ones."

## What it needs

Either a `position` field on `PollGroupModal` (smallest change — the service's
`updateGroup` already accepts it), or up/down controls on the group cards in
`PollServiceContainer` with a small reorder method.

Note archived groups already sort out of the default view, so this is about
ordering the active ones, not hiding stale ones.

## Done when

A circle admin can change the order of their groups and the Polls tab reflects
it.

## Answer

Up/down controls on the group cards, not a `position` field on the modal. A raw
integer invites collisions and makes an admin reason about numbers rather than
about order.

- `VotingService::reorderGroups(Circle, array $orderedIds)` owns the write.
  Positions are rewritten as a clean 0..N sequence rather than nudged, because
  every group starts at 0 — a scheme that only swapped stored values would do
  nothing on the first move. Foreign ids are ignored; omitted groups keep their
  relative order at the end.
- `PollServiceContainer::moveUp/moveDown` swap with the neighbour **as
  displayed**, then hand the whole circle's order to the service. This matters:
  with the active-only filter on, an active group's visible neighbour may not be
  its neighbour in the stored set, and swapping against a hidden archived row
  would look like the click did nothing. There is a test for exactly that.
- Arrows are hidden while a search is active — the neighbours you would be
  swapping are not the ones on screen — and disabled at the ends.

## Found while testing

`PollServiceContainer::groupUrl()` builds `communities.polls.show` from
`$group->slug`, so a group with a **null slug throws while rendering and takes
the whole Polls tab down**, not just its own card. `createGroup()` always
derives a slug, so this cannot happen through the app; it bit only because the
test created groups directly through the model.

Left as-is: `forum_groups` has the identical shape (nullable slug, slug-bound
route), so this is existing platform behaviour rather than something Polls
introduced. Worth knowing before anyone seeds or imports groups without a slug.
