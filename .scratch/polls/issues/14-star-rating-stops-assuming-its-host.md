# 14: The star rating component stops assuming its host

**What to build:** The star rating can be dropped into any form, not only one
whose state happens to be called `scores`. Today the component writes directly
to a hardcoded property path, so it silently does nothing anywhere else — a
shared component that only works in one place.

**Blocked by:** 07 (shared test schema builder).

**Status:** ready-for-agent

- [ ] The component takes the property it writes to, or emits an event the host handles
- [ ] The existing rating form behaves exactly as before
- [ ] Hover preview, keyboard focus, the accessible radiogroup and the point label all still work
- [ ] A test renders it under a different property name and confirms it writes there
