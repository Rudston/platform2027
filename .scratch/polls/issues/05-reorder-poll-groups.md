# No way to reorder poll groups

Status: ready-for-agent
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
