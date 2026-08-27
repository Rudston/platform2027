# 12: Tallying a rating Poll stops querying per response item

**What to build:** Tallying a rating Poll issues a constant number of queries
rather than one per response item. The tally eager-loads each response's items
and then reads the scale point off every one, so a Poll with 200 respondents and
5 options fires a thousand extra queries — on a path that runs on every page
view of an open Poll, and again when the Result freezes.

Compare the care already taken elsewhere in this service, where a comparable
count is documented as "one query, not one per row".

**Blocked by:** 07 (shared test schema builder).

**Status:** resolved

- [x] The scale point is loaded with the response items, not per item
- [x] Tallies produce identical Results to before — this is a query fix, not an arithmetic one
- [x] A test asserts the query count does not grow with the number of respondents

## Answer

One word in `VotingService::tally()`:

```php
->with('items.ratingScalePoint')   // was ->with('items')
```

A `Mark` carries the point's VALUE, so reading it off each item lazily cost one
query per ITEM. Measured before and after: **2 respondents = 8 queries, 8
respondents = 20**; now both cost the same, and instrumenting directly gives 5
queries flat for 2 or for 20 respondents (poll, question, options, responses,
items+points batched).

`test_tallying_a_rating_poll_costs_the_same_queries_whatever_the_turnout` asserts
the EQUALITY of two counts rather than an absolute number: a future constant
query lands in both, so only a genuinely per-respondent query fails it. It also
asserts exactly ONE `poll_rating_scale_points` query, which pins *why* the count
is flat rather than merely that it is. Verified to fail against the unfixed
`->with('items')`.

Arithmetic is unchanged and the diff touches no arithmetic line. Road alternates
5 and 3 across respondents so both averages are a real 4.0 rather than a repeated
constant — though what actually pins the tally is the untouched
`tests/Unit/PollTallyTest.php` (hand-computed, six AverageScore cases among them).

**Non-rating polls pay nothing**, verified empirically: where every
`rating_scale_point_id` is null, Laravel skips the nested eager load entirely —
no `poll_rating_scale_points` query at all, 5 queries either way. So no branch on
Response Shape is needed, and there is no regression for single_choice or
ranked_choice.

Also checked on the same path and found already sound: `validateRating()` fetches
its point ids once OUTSIDE the mark loop; `Tally` is pure; `frozenResult()`
deserialises a column; `Poll::roster()` is one query with a subselect;
`polls:freeze-results` already chunks. The tally was the only per-item read.

Tidying carried by this ticket (review findings): `queriesDuring()` moved out of
the test class into a shared **`Tests\Support\CountsQueries`** trait, with
`try/finally` so a throwing closure cannot leave query logging on for the rest of
the run, plus `queriesTouching()`; the rating-scale fixture consolidated into one
`ratingScaleWithPoints()` helper that the two pre-existing rating tests, the
draft `ratingPoll()` and the new `publishedRatingPollAnsweredBy()` all compose
from; and test people renamed off "Voter", which CONTEXT.md allows only for
election-shaped Polls, never a rating exercise.

Full suite: 327 passed.
