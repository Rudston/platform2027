<?php

namespace App\Enums;

enum LocatableType: string
{
    case Country              = 'App\Models\Demography\Country';
    case Province             = 'App\Models\Demography\Province';
    case DistrictMunicipality = 'App\Models\Demography\DistrictMunicipality';
    case LocalMunicipality    = 'App\Models\Demography\LocalMunicipality';
    case MainPlace            = 'App\Models\Demography\MainPlace';
    case City                 = 'App\Models\Demography\City';

    public function modelClass(): string
    {
        return $this->value;
    }

    public function label(): string
    {
        return match ($this) {
            self::Country              => __('geographic.level.country'),
            self::Province             => __('geographic.level.province'),
            self::DistrictMunicipality => __('geographic.level.district_municipality'),
            self::LocalMunicipality    => __('geographic.level.local_municipality'),
            self::MainPlace            => __('geographic.level.main_place'),
            self::City                 => __('geographic.level.city'),
        };
    }

    /**
     * Country-agnostic level for this SA-specific type, so callers can reason
     * about geography generically (e.g. $locatableType->locationLevel()).
     */
    public function locationLevel(): LocationLevel
    {
        return match ($this) {
            self::Country              => LocationLevel::Country,
            self::Province             => LocationLevel::Region,
            self::DistrictMunicipality => LocationLevel::District,
            self::LocalMunicipality    => LocationLevel::Local,
            self::City                 => LocationLevel::City,
            self::MainPlace            => LocationLevel::Place,
        };
    }

    /** Convenience proxy: is this the terminal (bottom) geographic level? */
    public function isTerminal(): bool
    {
        return $this->locationLevel()->isTerminal();
    }

    /**
     * Backing values of every TERMINAL locatable type — the "lowest level"
     * places (MainPlace in SA). Query-side counterpart to isTerminal(), for
     * `whereIn('locatable_type', …)` filters.
     *
     * NEVER identify the lowest level by `circles.depth`: the City branch is a
     * level shorter than the DistrictMunicipality one, so SA main places sit at
     * BOTH depth 3 and depth 4 today.
     *
     * @return list<string>
     */
    public static function terminalValues(): array
    {
        return array_values(array_map(
            fn (self $case): string => $case->value,
            array_filter(self::cases(), fn (self $case): bool => $case->isTerminal()),
        ));
    }
}
