# Decide the notification model for polls

Status: needs-info
Blocked by: a platform messaging service, not yet defined
Type: grilling

## Why

Nothing announces that a poll opened, closes soon, has a result, or was
cancelled, so a poll is only ever found by visiting its circle. That is the
biggest practical threat to turnout — and turnout is what makes a result
legitimate.

Deferred as UNRESOLVED, not unwanted. It needs a decision pass before any code.

## The open questions

Written up in full at the end of `POLLING_SERVICE.md`. In short:

1. Which of the four events warrant a message: cancelled (strongest — someone
   who voted in good faith is entitled to know it will not be counted),
   opened, closing soon, result available.
2. Consent. No notification preference exists on `users`, and no opt-out.
   Emailing every member because someone published a poll is a decision about
   unsolicited mail; POPIA makes it one to take deliberately.
3. Volume. A provincial circle has thousands of members, and membership caps
   mean one person sits in several circles. Needs queueing, batching, limits.
4. A "closing soon" reminder needs a scheduled job, which ADR-0001
   deliberately avoided for closing itself. Acceptable — it schedules OUTBOUND
   MESSAGES, never poll STATE — but the distinction has to be held.
5. Idempotency: a reminder must not fire twice. `polls.settings`, a
   notifications table, or the `metadata.email_log` pattern from `requests`.
6. Do NOT build an organiser-facing "remind those who haven't voted" button:
   it hands them exactly the list Q3c withholds by hiding roster names while a
   poll is open. An automated message to that set is arguably fine (no human
   sees the list); an affordance is not.
7. A "result available" notice should probably freeze the Result before
   sending, so the figure in the message and the figure on the page cannot
   come from two different computations.

## Smallest first step, if one is wanted before the rest is settled

Notify on **cancelled** only, to the people who actually responded. Small
audience, no scheduling, no consent ambiguity (they acted first), and it
discharges the one obligation that is uncomfortable to leave undone.

## Done when

The seven questions above have answers, written into CONTEXT.md / an ADR where
they are decisions rather than preferences. Then it can be specced.

## Update — blocked, not merely undecided

A platform-wide messaging service is planned but undefined. It will own delivery
across channels: email per user preference (those preferences do not exist yet
either), plus local/in-app messages.

That reframes this ticket. Consent, opt-out, channel and volume are NOT poll
questions — they belong to the messaging service, and answering them here would
produce a second, poll-shaped delivery path to unpick later. **Polls must not
grow their own notification mechanism in the meantime.**

What stays poll-specific, and should be carried over when the service exists:

- Cancelled is the obligation — someone who voted in good faith is entitled to
  know their vote will never be counted.
- No organiser-facing "remind those who haven't voted": it hands them exactly
  the list Q3c withholds by hiding roster names while a poll is open.
- A reminder job may send messages but must never write poll STATE (ADR-0001).
  The scheduler itself is fine — two commands already run.
- A "result available" notice should freeze the Result before sending, so the
  figure in the message and the figure on the page cannot come from two
  different computations.
- Idempotency: a reminder must not fire twice.

Unblock condition: the messaging service is defined far enough to say who may
be messaged, through which channel, and how preferences are stored.
