<?php

namespace App\Models\Communities;

use App\Contracts\Circleable;
use App\Contracts\Locatable;
use App\Models\Theme;
use App\Traits\HasCircle;
use App\Traits\HasLocation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThemeCommunity extends Model implements Circleable, Locatable
{
    use HasCircle, HasLocation;

    protected $guarded = [];

    public function theme(): BelongsTo
    {
        return $this->belongsTo(Theme::class);
    }

    public function circleName(): string
    {
        return $this->theme->name . " (" . $this->circle->locatable->circleNameShort() . ")";
    }

    public function circleDescription(): string
    {
       if ($this->circle->locatable->circleNameShort() == 'National') {
           return "This national-level community focuses on " . $this->theme->name;
       } elseif (str_contains($this->circle->locatable->circleNameShort(), 'Province')) {
           return "This provincial-level community focuses on " . $this->theme->name;
       } else {
           return "This community based in the ".$this->circle->locatable->circleNameShort()." focuses on " . $this->theme->name;
       }
    }
}
