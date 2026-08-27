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

**Status:** ready-for-agent

- [ ] No exception message is rendered into a form error
- [ ] Each refusal a user can actually trigger has a lang key, in `lang/en` and mirrored in `lang/pt`
- [ ] The exceptions keep their developer-facing detail for logs
- [ ] A test asserts a user-triggerable refusal renders its key, not the exception text
