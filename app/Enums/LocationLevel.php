<?php

namespace App\Enums;

/**
 * Country-agnostic geographic levels. SA-specific locatable types map onto
 * these via LocatableType::locationLevel(), so UI/services can reason about
 * geography generically (Province → Region, MainPlace → Place, etc.).
 */
enum LocationLevel: string
{
    case Country  = 'country';
    case Region   = 'region';   // Province, State, etc.
    case District = 'district'; // DistrictMunicipality, etc.
    case Local    = 'local';    // LocalMunicipality, etc.
    case City     = 'city';     // Cities (metropolitan) — has Place children, so NOT terminal
    case Place    = 'place';    // MainPlace, Neighbourhood, etc. — ALWAYS terminal

    public function label(): string
    {
        return match ($this) {
            self::Country  => 'Country',
            self::Region   => 'Region',
            self::District => 'District',
            self::Local    => 'Local',
            self::City     => 'City',
            self::Place    => 'Place',
        };
    }

    /**
     * Semantic hint: is this the bottom of the geographic hierarchy?
     * Only Place is terminal. City is NOT (it has Place children).
     *
     * NB: this is a hint for level-specific UI (e.g. the "no further sub-areas"
     * note). Whether a next column actually renders must still be driven by
     * whether child records exist in the DB, not by this flag alone.
     */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::Place => true,
            default     => false,
        };
    }
}
