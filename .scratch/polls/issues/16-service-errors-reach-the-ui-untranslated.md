# 16: Service errors reach the UI untranslated

**What to build:** Every message a respondent or organiser sees is translatable.
Today several are raw English strings thrown by the service and pushed straight
into form errors — "A poll needs at least two options.", "Poll [12] has already
ended." — while the keys sitting immediately above them in the same files show
the intended pattern.

CLAUDE.md is explicit that all UI strings and validation messages are
translated, and the fallback chain means an untranslated string is not merely
English for a pt_BR user: it is English with no way to translate it.

The messages themselves should stay as they are for logs and for developers.
What changes is that the UI stops rendering them verbatim.

**Blocked by:** 07 (shared test schema builder).

**Status:** resolved

- [x] No exception message is rendered into a form error
- [x] Each refusal a user can actually trigger has a lang key, in `lang/en` and mirrored in `lang/pt`
- [x] The exceptions keep their developer-facing detail for logs
- [x] A test asserts a user-triggerable refusal renders its key, not the exception text

## Answer

The service now throws keyed refusals; the UI renders the key, never the
message.

- `App\Support\Polls\TranslatableRefusal` — a contract with one method,
  `translationKey()`. Two carriers, so existing catches and tests keep their
  types: `PollRefusal extends RuntimeException` (lifecycle/respond refusals)
  and `InvalidPollInput extends InvalidArgumentException` (ballot and
  amendment input). Each keeps the full developer message — ids, rules — as
  the exception message, for logs.
- `VotingService` throws them at every user-triggerable refusal: publish /
  conclude / cancel guards, `guardAmendable`, `guardQualifyingDate`,
  `guardWindow`, `guardPairing`, `guardRatingScale`, `guardOptions`,
  `respond`'s entitlement re-check, and all of `validateMarks`. Pure
  invariants (re-snapshotting a responded electorate, answering/tallying a
  questionless poll) stay plain exceptions — a user cannot cause them, and
  the UI falls back to `polls.refusals.generic` if one ever surfaces.
- `PollModal` and `PollPage` (submit, publish — and now conclude/cancelPoll,
  which previously let a refusal 500) translate via the key instead of
  `$e->getMessage()`. Both call the ONE helper,
  `App\Support\Polls\RefusalMessage::for()` — key if the exception is a
  `TranslatableRefusal`, `polls.refusals.generic` otherwise — so the rule
  cannot fork between call sites.
- New `refusals.*` section in `lang/en/polls.php`, mirrored in `lang/pt` (in
  English, like the rest of that file — translating it wholesale is issue
  06). Three refusals reuse existing keys rather than duplicate text:
  `poll.min_options`, `poll.closes_before_opens`, `actions.not_amendable`.
- `PollModalTest::test_a_service_refusal_renders_its_lang_key_not_the_exception_text`
  covers both directions: the modal renders `__()` of the key; the respond
  path shows "Score every option." where the exception says "A rating
  response must score every option."; and the thrown exception still carries
  its developer message.
- Full poll suites green: 73 passed (VotingService + PollModal), 53 passed
  (the other poll feature tests).
