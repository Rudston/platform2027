# 12: Tallying a rating Poll stops querying per response item

**What to build:** Tallying a rating Poll issues a constant number of queries
rather than one per response item. The tally eager-loads each response's items
and then reads the scale point off every one, so a Poll with 200 respondents and
5 options fires a thousand extra queries — on a path that runs on every page
view of an open Poll, and again when the Result freezes.

Compare the care already taken elsewhere in this service, where a comparable
count is documented as "one query, not one per row".

**Blocked by:** 07 (shared test schema builder).

**Status:** ready-for-agent

- [ ] The scale point is loaded with the response items, not per item
- [ ] Tallies produce identical Results to before — this is a query fix, not an arithmetic one
- [ ] A test asserts the query count does not grow with the number of respondents
