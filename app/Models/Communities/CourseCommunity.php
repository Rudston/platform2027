<?php

namespace App\Models\Communities;

use App\Contracts\Circleable;
use App\Contracts\Communities\HasMembershipRules;
use App\Contracts\Locatable;
use App\Models\Communities\Concerns\HasStandardMembershipRules;
use App\Models\Course;
use App\Traits\HasCircle;
use App\Traits\HasLocation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CourseCommunity extends Model implements Circleable, HasMembershipRules, Locatable
{
    use HasCircle, HasLocation, HasStandardMembershipRules, SoftDeletes;

    protected $table = 'course_communities';

    protected $guarded = [];

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(
            Course::class,
            'course_course_community'
        )->withTimestamps();
    }

    public function circleName(): string
    {
        return $this->name ?? 'Unnamed Course Community';
    }
}
