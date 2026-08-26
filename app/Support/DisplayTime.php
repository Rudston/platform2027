<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * The boundary between how the application STORES time and how the interface
 * SPEAKS it.
 *
 * Storage and `now()` are UTC. Users type and read wall-clock times in
 * `config('app.display_timezone')`. Every conversion between the two goes
 * through here, so the assumption lives in one place rather than being spelled
 * out at each form field.
 *
 * Why this exists at all: until Polls, no feature asked a user to type an
 * absolute date and time — every date was rendered with diffForHumans(), which
 * hides the question. A poll opening at "12:21" is the first time the platform
 * had to know whose 12:21 was meant.
 */
class DisplayTime
{
    public static function timezone(): string
    {
        // `?:` rather than config()'s default argument: a key that EXISTS with a
        // null or empty value returns that value, so the default never fires
        // and the timezone comes back as an empty string.
        return (string) (config('app.display_timezone') ?: config('app.timezone') ?: 'UTC');
    }

    /**
     * A wall-clock string typed by a user (e.g. a datetime-local field's
     * "2026-08-26T12:21") read in the display timezone and returned in the
     * application's. Blank input yields null so an optional field stays empty.
     */
    public static function fromInput(?string $value): ?Carbon
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return Carbon::parse($value, self::timezone())->setTimezone(config('app.timezone'));
    }

    /**
     * A stored instant rendered for a datetime-local field, in the wall clock
     * the user typed it in — so reopening an edit form shows what they entered
     * rather than its UTC equivalent.
     */
    public static function toInput(?Carbon $value): string
    {
        return $value?->copy()->setTimezone(self::timezone())->format('Y-m-d\TH:i') ?? '';
    }

    /** A stored instant moved into the display timezone for rendering. */
    public static function forDisplay(?Carbon $value): ?Carbon
    {
        return $value?->copy()->setTimezone(self::timezone());
    }
}
