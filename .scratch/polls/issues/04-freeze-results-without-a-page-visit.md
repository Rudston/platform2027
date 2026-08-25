# A result only freezes when someone visits the poll

Status: needs-triage
Type: task

## Why

Closing is derived from the clock, so nothing HAPPENS when a poll's window
passes — that is ADR-0001 working as intended, and it is why no cron is
needed. But the Result has to be written down at some point, and today that
happens in `PollPage::mount()` on the first read after close.

Consequence: **a poll nobody visits stays unfrozen indefinitely.**

This is currently harmless. `freezeResult()` is idempotent, and recomputation
is deterministic, so a poll frozen a year late produces exactly the figure it
would have produced on the day. It stops being harmless if anything ever needs
frozen results WITHOUT a visit — a digest email, an export, a dashboard, a
completion action that grants a role to a winner.

## Options

1. Leave it. Freezing on read is honest and costs nothing until something else
   needs the data.
2. A scheduled command (`polls:freeze-results`) over closed, unfrozen,
   non-cancelled polls. Idempotent and adds-only, in the manner of
   `comments:check-moderation`. Note this is the same cron ADR-0001 avoided —
   acceptable here because it writes a RESULT, never a status, but the
   distinction must be explicit or someone will make it flip statuses too.
3. Freeze as a side effect of whatever first needs it (e.g. the notification
   job in issue 02).

Interacts with issue 02: a "result available" notice would want the Result
frozen before it sends.

## Done when

A decision, and either a command plus its test or a note in CLAUDE.md saying
freezing is read-triggered by design.
