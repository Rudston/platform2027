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
            self::Country              => 'Country',
            self::Province             => 'Province',
            self::DistrictMunicipality => 'District Municipality',
            self::LocalMunicipality    => 'Local Municipality',
            self::MainPlace            => 'Main Place',
            self::City                 => 'City',
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
}
