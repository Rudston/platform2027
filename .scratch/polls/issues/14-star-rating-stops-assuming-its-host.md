# 14: The star rating component stops assuming its host

**What to build:** The star rating can be dropped into any form, not only one
whose state happens to be called `scores`. Today the component writes directly
to a hardcoded property path, so it silently does nothing anywhere else — a
shared component that only works in one place.

**Blocked by:** 07 (shared test schema builder).

**Status:** resolved

- [x] The component takes the property it writes to, or emits an event the host handles
- [x] The existing rating form behaves exactly as before
- [x] Hover preview, keyboard focus, the accessible radiogroup and the point label all still work
- [x] A test renders it under a different property name and confirms it writes there

## Answer

The component takes the property, rather than emitting an event.

```blade
<x-polls.star-rating
    :points="$this->scalePoints"
    property="scores.{{ $option->id }}"
    :selected="$scores[$option->id] ?? null"
    :label="$option->label" />
```

Both options on the checklist would work; the property won because of what sits
directly beside it. When the scale's presentation is `select`, the same row
renders `<select wire:model="scores.{{ $option->id }}">`. A `property` prop makes
the star widget read as the same thing in the same place, and keeps the write a
single `$wire.set` — an event would have required the host to grow a handler
whose only job was to perform that write, and the two branches of one `@if`
would no longer look alike.

**The `optionId` prop is GONE, not defaulted.** It existed only to build
`scores.<optionId>`, so keeping it would have preserved the assumption the
ticket asked to remove — a host still could not choose the shape of its own
state, only the id inside a shape the component dictated. Now the component
knows nothing about how the host stores anything.

The path is interpolated with `@js($property)`, never concatenated into the JS
string. Verified in the rendered output: `scores.12` compiles to
`$wire.set('scores.12', 5)` — byte-identical to what the hardcoded version
emitted, which is what makes this a no-op for the existing form — while a
quote-bearing path becomes `$wire.set('it's.odd', 5)` instead of
terminating the string early and taking the form's JS down.

### Why it failed silently, which is the part worth remembering

Clicking a star did two things: set local Alpine state (`chosen`), and write to
Livewire. Only the second was hardcoded. So in any other form the stars still
filled in on click and the label still updated — the widget looked entirely
functional — while the score went to a property the host did not have. Nothing
threw, and there was nothing to notice.

### Tests

`tests/Feature/PollStarRatingTest.php` — five tests, the repo's first
`$this->blade()` component tests:
- writes to the property its host names (`answers.7`), and mentions `scores`
  nowhere at all;
- the existing `scores.12` path still emits one write per star, each carrying
  that point's id;
- the path is JS-encoded, not concatenated;
- radiogroup role, group aria-label, per-point aria-labels, hover/focus/blur
  wiring, `aria-checked`, the `aria-live` label region, and the stored point id
  translated into its star POSITION (2nd of 3) all survive;
- `disabled` emits no `$wire.set` and no hover wiring at all, while staying a
  radiogroup.

Each was verified red first — before the change the component could not render
without `optionId` at all, which is itself the clearest statement of the defect.

Full suite: 345 passed, 1220 assertions.