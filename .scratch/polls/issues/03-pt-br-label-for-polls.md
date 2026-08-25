# Portuguese label for the Polls service

Status: ready-for-human
Type: task

## Why

`services.name` for the `voting` row currently reads
`{"en": "Polls", "pt_BR": "Votações"}`.

The English was renamed when Q1 settled that **Poll** is the genus covering
elections, propositions AND rating exercises. "Votações" means votings/ballots
— the narrower sense Q1 explicitly rejected — so the two locales now describe
different scopes. "Sondagens" is the usual Portuguese for polls in the broader
sense, but this is a language judgement, not a code one.

## What it needs

A decision on the pt_BR string, then one update. Note `services.name` is
TRANSLATABLE JSON (documented in CLAUDE.md as of 7ff84a1):

- Through the model, a plain-string assignment sets only the CURRENT locale.
- Through the query builder it would replace the whole JSON and destroy the
  other locales. Do not do that.

Setting a specific locale explicitly:
`$service->setTranslation('name', 'pt_BR', '…')->save();`

## Also worth checking while in there

Whether `lang/pt/` and `lang/pt_BR/` need a `polls.php` at all — the new
`lang/en/polls.php` has ~119 keys and no translation. The fallback chain
(pt_BR → pt → en) means the UI works untranslated, so this is not blocking.
