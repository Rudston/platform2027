# Polls has no Portuguese translation

Status: ready-for-human
Type: task

## Why

`lang/en/polls.php` holds ~119 keys and there is no Portuguese counterpart, so
a pt_BR user sees the entire Polls UI in English — tab, group cards, the
compose modal, every respond form, the result panel and its explanatory notes.

Not blocking: the fallback chain resolves pt_BR → pt → en, so nothing breaks.
It is simply untranslated.

## Where the file goes

**`lang/pt/polls.php`**, not `lang/pt_BR/polls.php`.

`lang/pt/` is the shared Portuguese base and `lang/pt_BR/` holds Brazilian
overrides ONLY (CLAUDE.md, Internationalisation). The chain is real, not
aspirational — it is implemented in `AppServiceProvider::boot()` via
`Lang::determineLocalesUsing()`, which inserts each region locale's base
language before the fallback. Add a `lang/pt_BR/polls.php` only for strings
where Brazilian Portuguese genuinely differs from the shared base.

## Terminology constraints for whoever translates

Settled in CONTEXT.md and issue 03; do not re-decide them in translation:

- The service label is **Votações**, and *Sondagem* is RESERVED for the future
  Surveys service. Do not use *sondagem* anywhere in `polls.php`.
- A poll answerer is a **Respondent** — a distinct word from the forum
  "Participant" in English. Portuguese should keep them distinct too.
- The instruction above the options is a **Prompt**, not a "question".
- Never render `hide_voter_identities` as "anónimo"/"anônimo". Identity is
  stored; it is not a secret ballot. The English copy says "Hide who chose
  what" for exactly this reason.
- The instant-runoff result note explains that totals are FIRST PREFERENCES and
  the winner may not be the largest number. That explanation carries the
  meaning — a literal translation that loses it is worse than none.

## Done when

`lang/pt/polls.php` exists and a Portuguese speaker has read it against the
constraints above.
