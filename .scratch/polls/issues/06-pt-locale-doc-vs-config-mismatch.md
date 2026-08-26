# CLAUDE.md's Portuguese fallback chain does not match the config

Status: needs-info
Type: task

## Why

CLAUDE.md (Internationalisation → Key decisions) states:

> `lang/pt/` — shared Portuguese base
> `lang/pt_BR/` — Brazilian Portuguese overrides only
> Fallback chain: pt_BR → pt → en

The config does not implement that. `config('app.supported_locales')` is
`["en", "pt_BR"]` and `app.fallback_locale` is `en` — a single value, so a
missing pt_BR key falls straight through to English. `pt` is never consulted,
and is not reachable through the language switcher either.

Meanwhile `lang/pt/` exists and holds seven files (auth, communities, explore,
geographic, navigation, pages, ui) — real translation work that nothing loads.

## Which is wrong is not obvious

- If the DOC is right, `pt` should be added to `supported_locales` and a
  two-step fallback arranged (Laravel does not do this natively; it needs a
  custom loader or a merge at boot).
- If the CONFIG is right, `lang/pt/` is dead weight and the doc should say
  pt_BR → en, with the pt files either removed or folded into pt_BR.

Deciding needs to know whether a shared Portuguese base was a real plan (for
Mozambique / Angola / Portugal alongside Brazil) or an abandoned one.

## Found while

Answering issue 03 (the pt_BR label for Polls). Not caused by the Polls work
and does not affect it — Polls has no Portuguese translation either way.
